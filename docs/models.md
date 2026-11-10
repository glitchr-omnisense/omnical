# Models

All in `Omnical\Model`, readonly, checked when built (`InvalidArgumentException`).

## Events

- `Calendar`: id (the provider's: Google's calendar id, Graph's, a CalDAV collection's path),
  name, time zone, colour (`#RRGGBB`), `readOnly`, `primary`, owner, `version` (a ctag).
- `Event`: title, `start` and `end` (DateTimeImmutable, their zone kept), `allDay`, id,
  calendarId, `timeZone` (IANA: the start's when it has one), description, location, status,
  transparency, organizer, attendees, recurrence, `recurringEventId` and `originalStart` (an
  occurrence), uid (iCalendar's UID), url, etag, updated, source.
  - An all-day event holds dates: `start` at midnight of its first day, `end` at midnight after
    its last (exclusive), in its zone.
  - `busy()`: not cancelled and opaque. `overlaps()`, `duration()`, `attendee()`.
  - `stored()` gives the event back with the identifiers a provider gave it; `moved()` at
    another time.
- `Recurrence`: RRULE values (`FREQ=WEEKLY;BYDAY=MO`), EXRULEs, RDATEs, EXDATEs - data, never
  expanded here. `fromLines()` reads Google's `recurrence` lines, `toLines()` writes them (dates
  in the event's zone, as dates for an all-day event). `parts()`, `frequency()`, `interval()`,
  `count()`, `until()`, `days()`, `ends()`.
- `Attendee`: email (lower case, `mailto:` off), name, `ParticipationStatus`, `AttendeeRole`,
  organizer, resource (a room), self. The enums read every provider's words
  (`tentativelyAccepted`, `NEEDS-ACTION`, `OPT-PARTICIPANT`) and write iCalendar's (`ical()`).
- `EventQuery`: calendar, from, to (excluded), `expand` (each occurrence on its own, the
  default), limit, search, time zone.

## Free/busy

- `FreeBusyQuery`: calendars or addresses, from, to, time zone.
- `FreeBusy`: one calendar's taken spans (`BusySlot` with a `FreeBusyStatus`: busy, tentative,
  unavailable, out of office), or its `error`. `busy()`, `free($from, $to, $minimum)`, and
  `FreeBusy::common($calendars, $from, $to, $minimum)`: the spans free in all of them.

## Holidays

- `Holiday`: name, `first` and `last` day (included), country (ISO 3166-1), `nationwide` or its
  `regions` (the provider's subdivision codes: ISO 3166-2 where they exist), `types`
  (`HolidayType`: public, bank, school, optional, observance, authorities; the first the main
  one), language, local name, half day, id, comment, source. `holdsIn($region)`, `covers($day)`,
  `days()`.
- `HolidayQuery`: country, from, to (days, included), region, language, types (every type but
  school holidays when none). `HolidayQuery::year()`, `matches()`, `filter()` (each once, by day).
