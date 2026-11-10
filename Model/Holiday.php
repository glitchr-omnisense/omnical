<?php

namespace Omnical\Model;

use Omnical\Exception\InvalidArgumentException;

/**
 * A day off, or a run of days (school holidays): its first and last day
 * ($last included), its name in the language asked where the provider has
 * it, its country (ISO 3166-1 alpha-2), where it holds - the whole country
 * ($nationwide) or its regions (ISO 3166-2: "DE-BW", "FR-67" where the
 * provider says) - and its types, the first the main one.
 */
final readonly class Holiday
{
    public \DateTimeImmutable $first;

    public \DateTimeImmutable $last;

    public string $country;

    /** @var list<string> */
    public array $regions;

    /** @var non-empty-list<HolidayType> */
    public array $types;

    /**
     * @param list<string>      $regions
     * @param list<HolidayType> $types   Public when none is given
     */
    public function __construct(
        public string $name,
        \DateTimeInterface $first,
        ?\DateTimeInterface $last = null,
        string $country = '',
        public bool $nationwide = true,
        array $regions = [],
        array $types = [],
        public ?string $language = null,
        public ?string $localName = null,
        public bool $halfDay = false,
        public ?string $id = null,
        public ?string $comment = null,
        public ?string $source = null,
    ) {
        if ('' === trim($name)) {
            throw new InvalidArgumentException('A holiday needs its name.');
        }
        $this->first = self::day($first);
        $this->last = self::day($last ?? $first);
        if ($this->last < $this->first) {
            throw new InvalidArgumentException(\sprintf('The holiday "%s" ends before it starts.', $name));
        }
        $this->country = strtoupper(trim($country));
        $this->regions = array_values(array_unique(array_map(static fn (string $r) => strtoupper(trim($r)), array_filter($regions, static fn (string $r) => '' !== trim($r)))));
        $this->types = [] === $types ? [HolidayType::Public] : array_values(array_unique($types, \SORT_REGULAR));
    }

    /** Its main type. */
    public function type(): HolidayType
    {
        return $this->types[0];
    }

    public function is(HolidayType $type): bool
    {
        return \in_array($type, $this->types, true);
    }

    /** Whether the day given is one of its days (its date as written, whatever its zone). */
    public function covers(\DateTimeInterface $day): bool
    {
        $day = $day->format('Y-m-d');

        return $day >= $this->first->format('Y-m-d') && $day <= $this->last->format('Y-m-d');
    }

    /** How many days it lasts. */
    public function days(): int
    {
        return (int) $this->first->diff($this->last)->days + 1;
    }

    /**
     * Whether it holds in a region (ISO 3166-2): it is nationwide, or the
     * region is one of its own, or one holds the other by its code ("DE-BY"
     * and "DE-BY-A"). Null asks for the country as a whole: nationwide ones
     * only.
     */
    public function holdsIn(?string $region): bool
    {
        if ($this->nationwide) {
            return true;
        }
        if (null === $region) {
            return false;
        }
        $region = strtoupper(trim($region));
        foreach ($this->regions as $own) {
            if ($own === $region || str_starts_with($own, $region.'-') || str_starts_with($region, $own.'-')) {
                return true;
            }
        }

        return false;
    }

    private static function day(\DateTimeInterface $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date->format('Y-m-d'), new \DateTimeZone('UTC'));
    }
}
