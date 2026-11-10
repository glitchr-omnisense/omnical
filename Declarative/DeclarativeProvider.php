<?php

namespace Omnical\Declarative;

use Omnical\Capabilities;
use Omnical\Exception\InvalidConfigException;
use Omnical\HolidaysInterface;
use Omnical\Model\Holiday;
use Omnical\Model\HolidayQuery;
use Omnical\Model\HolidayType;
use Omnisense\Contracts\Policy\DeclaresUsagePolicyInterface;
use Omnisense\Contracts\Policy\UsagePolicy;

/**
 * A holidays provider made of a definition, no class of its own: the
 * definition's "holidays" endpoints called with the query's country,
 * region, language and days ({country}, {region}, {language}, {from},
 * {to}, and {year} when an endpoint splits by year), the endpoints tagged
 * with holiday types called only when the query asks for one of them
 * (school holidays apart), each item read into a Holiday from the fields
 * the map names:
 *
 *   name (required), date (required, its first day), end (its last day,
 *   included), localName, language, country, nationwide, regions (ISO
 *   3166-2 codes), types (a value or a list: public, bank, school,
 *   optional, observance, authorities - "values" maps the provider's
 *   words), halfDay, id, comment.
 *
 * Then the query filters them (HolidayQuery::filter(): its days, its
 * region, its types; each once). The definition's "describe" gives the
 * Capabilities: countries, regions, types.
 *
 * The calendar contracts are not declared: they need a user's consent
 * (OAuth), writes and conditional requests, which a provider in code does.
 */
final class DeclarativeProvider implements HolidaysInterface, DeclaresUsagePolicyInterface
{
    public const FIELDS = ['id', 'date', 'end', 'name', 'localName', 'language', 'country', 'nationwide', 'regions', 'types', 'halfDay', 'comment'];

    public function __construct(private readonly DeclarativeClient $client)
    {
        $definition = $client->definition();
        if ([Capabilities::HOLIDAYS] !== $definition->capabilities) {
            throw new InvalidConfigException(\sprintf('The "%s" definition keeps %s: a declared omnical provider keeps "holidays" only (calendars need a provider in code).', $definition->name, implode(', ', $definition->capabilities) ?: 'nothing'));
        }
        if (null !== $definition->family && 'omnical' !== $definition->family) {
            throw new InvalidConfigException(\sprintf('The "%s" definition is %s\'s, not omnical\'s.', $definition->name, $definition->family));
        }
        $fields = $definition->fields(Capabilities::HOLIDAYS);
        if ([] !== $unknown = array_diff($fields, self::FIELDS)) {
            throw new InvalidConfigException(\sprintf('The "%s" definition maps %s, which a Holiday does not hold (%s).', $definition->name, implode(', ', $unknown), implode(', ', self::FIELDS)));
        }
        if ([] !== $missing = array_diff(['date', 'name'], $fields)) {
            throw new InvalidConfigException(\sprintf('The "%s" definition must map a holiday\'s %s.', $definition->name, implode(' and ', $missing)));
        }
    }

    public function getName(): string
    {
        return $this->client->definition()->name;
    }

    public function getTitle(): string
    {
        return $this->client->definition()->title;
    }

    public function usagePolicy(): UsagePolicy
    {
        return $this->client->definition()->policy;
    }

    public function capabilities(): Capabilities
    {
        $describe = $this->client->definition()->describe;
        $countries = \is_array($describe['countries'] ?? null) ? array_values(array_filter(array_map(static fn (mixed $c) => \is_string($c) ? strtoupper($c) : null, $describe['countries']))) : [];
        $types = \is_array($describe['types'] ?? null) ? array_values(array_filter(array_map(static fn (mixed $t) => \is_string($t) ? HolidayType::tryFrom($t) : null, $describe['types']))) : [];

        return new Capabilities(holidays: true, regions: true === ($describe['regions'] ?? false), holidayTypes: $types, countries: $countries, policy: $this->usagePolicy());
    }

    public function holidays(HolidayQuery $query): array
    {
        $args = ['country' => $query->country, 'region' => $query->region, 'language' => $query->language, 'from' => $query->from, 'to' => $query->to];
        $holidays = [];
        foreach ($this->client->call(Capabilities::HOLIDAYS, $args, array_map(static fn (HolidayType $t) => $t->value, $query->wanted())) as $item) {
            $holiday = $this->holiday($item, $query);
            if (null !== $holiday) {
                $holidays[] = $holiday;
            }
        }

        return $query->filter($holidays);
    }

    /** @param array<string, mixed> $item */
    private function holiday(array $item, HolidayQuery $query): ?Holiday
    {
        $name = self::text($item['name'] ?? null);
        $first = self::date($item['date'] ?? null);
        if (null === $name || null === $first) {
            return null;
        }
        $regions = \is_array($item['regions'] ?? null) ? array_values(array_filter(array_map(self::text(...), $item['regions']))) : (null === ($r = self::text($item['regions'] ?? null)) ? [] : [$r]);
        $types = $item['types'] ?? null;
        $types = array_map(static fn (mixed $t) => HolidayType::read(self::text($t)), \is_array($types) ? $types : (null === $types ? [] : [$types]));
        $nationwide = $item['nationwide'] ?? null;

        return new Holiday(
            $name,
            $first,
            self::date($item['end'] ?? null),
            self::text($item['country'] ?? null) ?? $query->country,
            \is_bool($nationwide) ? $nationwide : [] === $regions,
            $regions,
            array_values($types),
            self::text($item['language'] ?? null) ?? $query->language,
            self::text($item['localName'] ?? null),
            true === ($item['halfDay'] ?? false),
            self::text($item['id'] ?? null),
            self::text($item['comment'] ?? null),
            $this->getName(),
        );
    }

    private static function text(mixed $value): ?string
    {
        return \is_scalar($value) && '' !== trim((string) $value) ? trim((string) $value) : null;
    }

    private static function date(mixed $value): ?\DateTimeImmutable
    {
        $value = self::text($value);
        if (null === $value || !preg_match('~^\d{4}-\d{2}-\d{2}~', $value)) {
            return null;
        }

        return \DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10), new \DateTimeZone('UTC')) ?: null;
    }
}
