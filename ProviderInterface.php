<?php

namespace Omnical;

/**
 * One source of calendars or holidays, configured: Google Calendar, Microsoft
 * Graph, a CalDAV server, OpenHolidays, Nager.Date... Its Capabilities say
 * what it can be asked, by contract - a calendar's events, read and written
 * (CalendarEventsInterface), busy time (FreeBusyInterface), a country's
 * holidays (HolidaysInterface) - and its usage policy.
 */
interface ProviderInterface extends \Omnisense\Contracts\Provider\ProviderInterface
{
    /** The factory's name: "google", "caldav", "openholidays"... */
    public function getName(): string;

    public function getTitle(): string;

    public function capabilities(): Capabilities;
}
