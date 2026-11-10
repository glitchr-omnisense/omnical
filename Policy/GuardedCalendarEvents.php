<?php

namespace Omnical\Policy;

use Omnical\CalendarEventsInterface;
use Omnical\Model\Event;
use Omnical\Model\EventQuery;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's calendars behind its guard: each call the operation named
 * after its method - "calendars", "events", "event", "create", "update",
 * "delete".
 *
 * @extends Guarded<CalendarEventsInterface>
 */
final class GuardedCalendarEvents extends Guarded implements CalendarEventsInterface
{
    /** @param array{key?: string, ip?: string, user?: string} $context what a limit per user or key counts by */
    public function __construct(CalendarEventsInterface $provider, PolicyGuard $guard, private readonly array $context = [])
    {
        parent::__construct($provider, $guard);
    }

    public function calendars(): array
    {
        return $this->guard->call('calendars', fn () => $this->provider->calendars(), $this->context);
    }

    public function events(EventQuery $query): array
    {
        return $this->guard->call('events', fn () => $this->provider->events($query), $this->context);
    }

    public function event(string $calendarId, string $eventId): ?Event
    {
        return $this->guard->call('event', fn () => $this->provider->event($calendarId, $eventId), $this->context);
    }

    public function create(Event $event): Event
    {
        return $this->guard->call('create', fn () => $this->provider->create($event), $this->context);
    }

    public function update(Event $event): Event
    {
        return $this->guard->call('update', fn () => $this->provider->update($event), $this->context);
    }

    public function delete(string $calendarId, string $eventId, ?string $etag = null): void
    {
        $this->guard->call('delete', fn () => $this->provider->delete($calendarId, $eventId, $etag), $this->context);
    }
}
