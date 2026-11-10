<?php

namespace Omnical\Model;

use Omnical\Exception\InvalidArgumentException;

/**
 * An event of a calendar: what, when, where, who.
 *
 * Time zones: $start and $end carry their zone (a DateTimeImmutable's), and
 * $timeZone names the event's own (IANA: "Europe/Paris"), the zone its
 * recurrence follows across daylight saving - the start's zone when it has
 * an IANA name, otherwise as given. An all-day event ($allDay) holds dates:
 * $start at midnight of its first day, $end at midnight after its last
 * (exclusive, as iCalendar, Google and Graph say it).
 *
 * Recurrence: a series is one Event with its $recurrence (RRULE kept as
 * data); an occurrence of it, as a provider expands it, is an Event with
 * $recurringEventId (the series' id) and $originalStart (where the rule put
 * it, before it was moved).
 *
 * Writing: a new event has no $id; the provider's create() gives it back
 * with its $id, $calendarId, $uid and $etag. update() sends $etag as the
 * condition (If-Match) where the provider honours it: a ConflictException
 * when it changed since it was read.
 */
final readonly class Event
{
    public \DateTimeImmutable $start;

    public \DateTimeImmutable $end;

    public ?string $timeZone;

    /**
     * @param list<Attendee> $attendees
     */
    public function __construct(
        public string $title,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        public bool $allDay = false,
        public ?string $id = null,
        public ?string $calendarId = null,
        ?string $timeZone = null,
        public ?string $description = null,
        public ?string $location = null,
        public EventStatus $status = EventStatus::Confirmed,
        public Transparency $transparency = Transparency::Opaque,
        public ?Attendee $organizer = null,
        public array $attendees = [],
        public ?Recurrence $recurrence = null,
        public ?string $recurringEventId = null,
        public ?\DateTimeImmutable $originalStart = null,
        public ?string $uid = null,
        public ?string $url = null,
        public ?string $etag = null,
        public ?\DateTimeImmutable $updated = null,
        public ?string $source = null,
    ) {
        $start = \DateTimeImmutable::createFromInterface($start);
        $end = \DateTimeImmutable::createFromInterface($end);
        if (null !== $timeZone) {
            Zone::check($timeZone);
        }
        $this->timeZone = $timeZone ?? Zone::nameOf($start);
        if ($allDay) {
            $zone = null !== $this->timeZone ? new \DateTimeZone($this->timeZone) : $start->getTimezone();
            $start = new \DateTimeImmutable($start->format('Y-m-d'), $zone);
            $end = new \DateTimeImmutable($end->format('Y-m-d'), $zone);
            if ($end <= $start) {
                throw new InvalidArgumentException(\sprintf('The all-day event "%s" ends on the day it starts or before: its end is the day after its last one.', $title));
            }
        } elseif ($end < $start) {
            throw new InvalidArgumentException(\sprintf('The event "%s" ends before it starts.', $title));
        }
        $this->start = $start;
        $this->end = $end;
    }

    /** Its length in seconds. */
    public function duration(): int
    {
        return $this->end->getTimestamp() - $this->start->getTimestamp();
    }

    /** Whether it takes some of the time between $from and $to (both moments; $to excluded). */
    public function overlaps(\DateTimeInterface $from, \DateTimeInterface $to): bool
    {
        return $this->start < $to && $this->end > $from;
    }

    /** A series: it holds a recurrence. */
    public function isRecurring(): bool
    {
        return null !== $this->recurrence;
    }

    /** One occurrence of a series, as a provider expanded it. */
    public function isOccurrence(): bool
    {
        return null !== $this->recurringEventId;
    }

    /** Whether the time it takes is busy time: not cancelled, not transparent. */
    public function busy(): bool
    {
        return EventStatus::Cancelled !== $this->status && Transparency::Opaque === $this->transparency;
    }

    public function attendee(string $email): ?Attendee
    {
        $email = strtolower(trim((string) preg_replace('~^mailto:~i', '', $email)));
        foreach ($this->attendees as $attendee) {
            if ($attendee->email === $email) {
                return $attendee;
            }
        }

        return null;
    }

    /** The same event as a provider stored it: its identifiers and version. */
    public function stored(string $id, string $calendarId, ?string $etag = null, ?string $uid = null, ?string $url = null, ?\DateTimeImmutable $updated = null, ?string $source = null): self
    {
        return new self(
            $this->title, $this->start, $this->end, $this->allDay, $id, $calendarId, $this->timeZone, $this->description, $this->location,
            $this->status, $this->transparency, $this->organizer, $this->attendees, $this->recurrence, $this->recurringEventId, $this->originalStart,
            $uid ?? $this->uid, $url ?? $this->url, $etag, $updated ?? $this->updated, $source ?? $this->source,
        );
    }

    /** The same event at another time (its zone and everything else kept). */
    public function moved(\DateTimeInterface $start, \DateTimeInterface $end): self
    {
        return new self(
            $this->title, $start, $end, $this->allDay, $this->id, $this->calendarId, $this->timeZone, $this->description, $this->location,
            $this->status, $this->transparency, $this->organizer, $this->attendees, $this->recurrence, $this->recurringEventId, $this->originalStart,
            $this->uid, $this->url, $this->etag, $this->updated, $this->source,
        );
    }
}
