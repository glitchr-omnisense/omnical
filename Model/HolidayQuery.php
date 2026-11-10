<?php

namespace Omnical\Model;

use Omnical\Exception\InvalidArgumentException;

/**
 * A country's holidays between two days (both included): in one of its
 * regions too when $region is given (ISO 3166-2, "DE-BW"), every region's
 * otherwise (each Holiday says where it holds); their names in $language
 * (ISO 639-1) where the provider has it; of the $types asked - every type
 * but school holidays when none is.
 */
final readonly class HolidayQuery
{
    public string $country;

    public \DateTimeImmutable $from;

    public \DateTimeImmutable $to;

    public ?string $region;

    public ?string $language;

    /** @param list<HolidayType> $types */
    public function __construct(
        string $country,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        ?string $region = null,
        ?string $language = null,
        public array $types = [],
    ) {
        $country = strtoupper(trim($country));
        if (!preg_match('~^[A-Z]{2}$~', $country)) {
            throw new InvalidArgumentException(\sprintf('"%s" is no country code (ISO 3166-1 alpha-2: "FR").', $country));
        }
        $region = null === $region ? null : strtoupper(trim($region));
        if (null !== $region && !preg_match('~^'.$country.'-[A-Z0-9]{1,3}(?:-[A-Z0-9]{1,3})?$~', $region)) {
            throw new InvalidArgumentException(\sprintf('"%s" is no region of %s (ISO 3166-2: "%s-...").', $region, $country, $country));
        }
        $language = null === $language ? null : strtolower(trim($language));
        if (null !== $language && !preg_match('~^[a-z]{2}$~', $language)) {
            throw new InvalidArgumentException(\sprintf('"%s" is no language code (ISO 639-1: "fr").', $language));
        }
        $this->country = $country;
        $this->region = $region;
        $this->language = $language;
        $this->from = new \DateTimeImmutable($from->format('Y-m-d'), new \DateTimeZone('UTC'));
        $this->to = new \DateTimeImmutable($to->format('Y-m-d'), new \DateTimeZone('UTC'));
        if ($this->to < $this->from) {
            throw new InvalidArgumentException('A holiday query needs its last day after its first.');
        }
    }

    /** @param list<HolidayType> $types */
    public static function year(string $country, int $year, ?string $region = null, ?string $language = null, array $types = []): self
    {
        return new self($country, new \DateTimeImmutable($year.'-01-01'), new \DateTimeImmutable($year.'-12-31'), $region, $language, $types);
    }

    /** @return list<HolidayType> the types asked: every one but School when none is */
    public function wanted(): array
    {
        return [] !== $this->types ? $this->types : array_values(array_filter(HolidayType::cases(), static fn (HolidayType $t) => HolidayType::School !== $t));
    }

    /** Whether a holiday answers the query: its days, its region, its types. */
    public function matches(Holiday $holiday): bool
    {
        return $holiday->last >= $this->from && $holiday->first <= $this->to
            && (null === $this->region || $holiday->holdsIn($this->region))
            && [] !== array_intersect(array_map(static fn (HolidayType $t) => $t->value, $holiday->types), array_map(static fn (HolidayType $t) => $t->value, $this->wanted()));
    }

    /**
     * The holidays that answer, each once, by first day then name.
     *
     * @param iterable<Holiday> $holidays
     *
     * @return list<Holiday>
     */
    public function filter(iterable $holidays): array
    {
        $kept = [];
        foreach ($holidays as $holiday) {
            if ($this->matches($holiday)) {
                $kept[$holiday->first->format('Y-m-d').'|'.$holiday->last->format('Y-m-d').'|'.$holiday->name.'|'.implode(',', $holiday->regions).'|'.$holiday->type()->value] = $holiday;
            }
        }
        ksort($kept);

        return array_values($kept);
    }
}
