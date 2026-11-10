<?php

namespace Omnical;

use Omnical\Exception\ConflictException;
use Omnical\Exception\NotSupportedException;
use Omnical\Model\Calendar;
use Omnical\Model\Event;
use Omnical\Model\EventQuery;

/**
 * The calendars a user opened to the site, their events read and written
 * ("calendar-events"). A provider that only reads says so (Capabilities::
 * $write false) and throws NotSupportedException on a write.
 */
interface CalendarEventsInterface extends ProviderInterface
{
    /** @return list<Calendar> the user's calendars, the primary one first where the provider says which */
    public function calendars(): array;

    /** @return list<Event> the events in the query's span, the earliest first */
    public function events(EventQuery $query): array;

    /** One event, or null when it is not there (any more). */
    public function event(string $calendarId, string $eventId): ?Event;

    /**
     * A new event in its calendar ($event->calendarId), given back as stored:
     * its id, uid and etag.
     *
     * @throws NotSupportedException for a provider that only reads, or a recurrence it cannot say
     */
    public function create(Event $event): Event;

    /**
     * An event written over the stored one ($event->id, $event->calendarId),
     * given back as stored.
     *
     * @throws ConflictException when $event->etag is given and the event changed since
     */
    public function update(Event $event): Event;

    /**
     * An event deleted; nothing when it was not there.
     *
     * @throws ConflictException when $etag is given and the event changed since
     */
    public function delete(string $calendarId, string $eventId, ?string $etag = null): void;
}
