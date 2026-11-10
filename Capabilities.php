<?php

namespace Omnical;

use Omnical\Model\HolidayType;
use Omnisense\Contracts\Policy\UsagePolicy;
use Omnisense\Contracts\Provider\CapabilitiesInterface;

/**
 * What a provider is, said before it is asked anything: the contracts it
 * keeps ("calendar-events", "free-busy", "holidays"), what its answers carry,
 * the countries and holiday types it covers, and its usage policy
 * (omnisense/contracts' UsagePolicy: rate limits, rights, requirements).
 */
final readonly class Capabilities implements CapabilitiesInterface
{
    public const CALENDAR_EVENTS = 'calendar-events';
    public const FREE_BUSY = 'free-busy';
    public const HOLIDAYS = 'holidays';

    private UsagePolicy $policy;

    /**
     * @param bool              $calendarEvents a user's calendars and their events (CalendarEventsInterface)
     * @param bool              $write          events may be created, updated, deleted
     * @param bool              $expand         it gives each occurrence of a series (EventQuery::$expand)
     * @param bool              $attendees      events carry their attendees and answers
     * @param bool              $freeBusy       busy time (FreeBusyInterface)
     * @param bool              $people         free/busy of people by their address, not only of the user's calendars
     * @param bool              $holidays       a country's holidays (HolidaysInterface)
     * @param bool              $regions        holidays say the regions they hold in
     * @param list<HolidayType> $holidayTypes   the holiday types it gives
     * @param list<string>      $countries      the countries its holidays cover (ISO 3166-1 alpha-2); empty: any it has data for
     */
    public function __construct(
        public bool $calendarEvents = false,
        public bool $write = false,
        public bool $expand = false,
        public bool $attendees = false,
        public bool $freeBusy = false,
        public bool $people = false,
        public bool $holidays = false,
        public bool $regions = false,
        public array $holidayTypes = [],
        public array $countries = [],
        ?UsagePolicy $policy = null,
    ) {
        $this->policy = $policy ?? UsagePolicy::unknown(UsagePolicy::UNNAMED);
    }

    public function policy(): UsagePolicy
    {
        return $this->policy;
    }

    /** @return list<string> the contracts it keeps: calendar-events, free-busy, holidays */
    public function contracts(): array
    {
        return array_keys(array_filter([self::CALENDAR_EVENTS => $this->calendarEvents, self::FREE_BUSY => $this->freeBusy, self::HOLIDAYS => $this->holidays]));
    }

    /** Whether its holidays may cover this country: it names it, or names none. */
    public function coversCountry(string $country): bool
    {
        return $this->holidays && ([] === $this->countries || \in_array(strtoupper($country), $this->countries, true));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'contracts' => $this->contracts(),
            'write' => $this->write, 'expand' => $this->expand, 'attendees' => $this->attendees, 'people' => $this->people, 'regions' => $this->regions,
            'holidayTypes' => array_map(static fn (HolidayType $t) => $t->value, $this->holidayTypes),
            'countries' => $this->countries,
            'policy' => $this->policy->toArray(),
        ];
    }
}
