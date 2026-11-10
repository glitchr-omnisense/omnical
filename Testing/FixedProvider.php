<?php

namespace Omnical\Testing;

use Omnical\CalendarEventsInterface;
use Omnical\Capabilities;
use Omnical\Exception\ConflictException;
use Omnical\Exception\InvalidArgumentException;
use Omnical\FreeBusyInterface;
use Omnical\HolidaysInterface;
use Omnical\Model\BusySlot;
use Omnical\Model\Calendar;
use Omnical\Model\Event;
use Omnical\Model\EventQuery;
use Omnical\Model\EventStatus;
use Omnical\Model\FreeBusy;
use Omnical\Model\FreeBusyQuery;
use Omnical\Model\FreeBusyStatus;
use Omnical\Model\Holiday;
use Omnical\Model\HolidayQuery;

/**
 * A provider that answers from what it was given, with no network: an
 * application's tests, the harness's bare script. Calendars, their events
 * (written in memory: create() gives an id and an etag, update() checks the
 * etag and bumps it, delete() forgets), the busy time their events take, a
 * country's holidays. It does not expand recurrences: a series is given as
 * it was stored.
 */
final class FixedProvider implements CalendarEventsInterface, FreeBusyInterface, HolidaysInterface
{
    /** @var array<string, Event> by "calendar id event id" */
    private array $events = [];

    private int $next = 1;

    /**
     * @param list<Calendar> $calendars
     * @param list<Event>    $events    each with its id and calendar id
     * @param list<Holiday>  $holidays
     */
    public function __construct(private readonly array $calendars = [], array $events = [], private readonly array $holidays = [], private readonly string $name = 'fixed')
    {
        foreach ($events as $event) {
            if (null === $event->id || null === $event->calendarId) {
                throw new InvalidArgumentException('A fixed event needs its id and its calendar id.');
            }
            $this->events[$event->calendarId.' '.$event->id] = $event;
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return 'Fixed calendars';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(
            calendarEvents: true,
            write: true,
            attendees: true,
            freeBusy: true,
            holidays: true,
            regions: true,
            holidayTypes: array_values(array_unique(array_merge([], ...array_map(static fn (Holiday $h) => $h->types, $this->holidays)), \SORT_REGULAR)),
            countries: array_values(array_unique(array_filter(array_map(static fn (Holiday $h) => $h->country, $this->holidays)))),
        );
    }

    public function calendars(): array
    {
        return $this->calendars;
    }

    public function events(EventQuery $query): array
    {
        $events = array_values(array_filter($this->events, static fn (Event $e) => $e->calendarId === $query->calendarId && $e->overlaps($query->from, $query->to)
            && (null === $query->search || str_contains(mb_strtolower($e->title.' '.$e->description.' '.$e->location), mb_strtolower($query->search)))));
        usort($events, static fn (Event $a, Event $b) => $a->start <=> $b->start);

        return \array_slice($events, 0, $query->limit);
    }

    public function event(string $calendarId, string $eventId): ?Event
    {
        return $this->events[$calendarId.' '.$eventId] ?? null;
    }

    public function create(Event $event): Event
    {
        $calendar = $event->calendarId ?? throw new InvalidArgumentException('An event is created in a calendar: its calendarId.');
        $stored = $event->stored($this->name.'-'.$this->next++, $calendar, '1', $event->uid ?? bin2hex(random_bytes(8)).'@omnical', source: $this->name);

        return $this->events[$calendar.' '.$stored->id] = $stored;
    }

    public function update(Event $event): Event
    {
        $key = ($event->calendarId ?? '').' '.($event->id ?? '');
        $old = $this->events[$key] ?? throw new InvalidArgumentException('No such event to update.');
        if (null !== $event->etag && $event->etag !== $old->etag) {
            throw new ConflictException(\sprintf('%s answered 412 for the event: it changed since it was read.', $this->name), 412);
        }

        return $this->events[$key] = $event->stored((string) $event->id, (string) $event->calendarId, (string) ((int) $old->etag + 1), $old->uid, source: $this->name);
    }

    public function delete(string $calendarId, string $eventId, ?string $etag = null): void
    {
        $old = $this->events[$calendarId.' '.$eventId] ?? null;
        if (null !== $old && null !== $etag && $etag !== $old->etag) {
            throw new ConflictException(\sprintf('%s answered 412 for the event: it changed since it was read.', $this->name), 412);
        }
        unset($this->events[$calendarId.' '.$eventId]);
    }

    public function freeBusy(FreeBusyQuery $query): array
    {
        $answers = [];
        foreach ($query->calendars as $calendar) {
            $slots = [];
            foreach ($this->events as $event) {
                if ($event->calendarId === $calendar && $event->busy() && $event->overlaps($query->from, $query->to)) {
                    $slots[] = new BusySlot($event->start, $event->end, EventStatus::Tentative === $event->status ? FreeBusyStatus::Tentative : FreeBusyStatus::Busy);
                }
            }
            $known = [] !== array_filter($this->calendars, static fn (Calendar $c) => $c->id === $calendar);
            $answers[] = new FreeBusy($calendar, $slots, $known ? null : 'notFound', $this->name);
        }

        return $answers;
    }

    public function holidays(HolidayQuery $query): array
    {
        return $query->filter(array_filter($this->holidays, static fn (Holiday $h) => $h->country === $query->country));
    }
}
