# Changelog

## [Unreleased]

## [1.0.1] - 2026-11-13

### Changed
- README: the first call prints where each regional holiday holds and says what a query without a
  region answers - the nationwide holidays and every region's (as `HolidayQuery` documents), so
  "Abolition de l'esclavage" comes six times, one date per overseas region; how to ask one region,
  or keep the nationwide alone. No change of behaviour.

## [1.0.0] - 2026-11-13

The first stable version. It requires `omnisense/contracts` `^1.0` with no stability flag, so `composer require omnisense/omnical` installs it in a project with Composer's default minimum-stability; the 1.x series follows semantic versioning. A tag `vX.Y.Z` is released by `.github/workflows/release.yml` once the package's CI passes on it.

- 2026-11-13, for 1.0.0: `Http\JsonClient` always takes the client through `NoRetry::of()`; the fallback
  for a kernel older than omnisense/contracts 1568f2c (the client kept as given when `NoRetry` was
  missing) is gone, since `^1.0` always has it. The README opens on the install line and a first
  call that needs no account (the public holidays of OpenHolidays).

- 2026-11-10, writes sent once: `Http\JsonClient` (every call of every provider, `Http\RawClient`
  and the OAuth client included) keeps the HTTP client it is given with its retries
  off (omnisense/contracts' `Http\NoRetry`, which omnisense/contracts 1568f2c brings; with an older
  kernel the client is kept as given), so Symfony's `RetryableHttpClient` never sends an event created, moved or deleted
  twice. A `MockHttpClient` is kept as given. docs/policies.md. Tests/NoRetryTest.

- 2026-11-10, on omnisense/contracts' round 2 (5bac28b or later, still `^1.0@dev`):
  `Http\RawClient` is the kernel's `JsonClient::raw()` with omnical's exceptions, its own copy of
  the exchange gone (same constructor, same `request()`); `Http\Answer` gains `url`, the URL that
  answered after redirects (empty for an answer made by hand), and `Answer::of()` from the
  kernel's. Headers with several values come whole.
- 2026-11-10: the family written, on omnisense/contracts from the first day.
  - Three contracts: `CalendarEventsInterface` (calendars, events in a span, one event, create,
    update, delete), `FreeBusyInterface`, `HolidaysInterface`.
  - Models: Calendar, Event (its zone, all-day dates with an exclusive end, a series or an
    occurrence of one, ETag), Recurrence (RRULE, EXRULE, RDATE, EXDATE kept as data, read from
    and written to iCalendar lines), Attendee with ParticipationStatus and AttendeeRole,
    EventStatus, Transparency, EventQuery, FreeBusyQuery, FreeBusy (`free()`, `common()`),
    BusySlot, FreeBusyStatus, Holiday (first and last day, regions, types, half days),
    HolidayQuery (`filter()`), HolidayType.
  - Capabilities by contract, Registry (`calendarEvents()`, `freeBusy()`, `holidays()` guarded,
    a limit per user counted by the user given, `covering()` by country), Config,
    ProviderFactory, Policy\Guarded*.
  - Http: JsonClient, Data, Redaction (the kernel's), and RawClient for what is not JSON
    (CalDAV's XML, a 204, a header read); a status is the exception's code, 401 an
    AuthenticationException, 412 a ConflictException.
  - Auth: secrets by reference (SecretsInterface, EnvSecrets, ArraySecrets), OAuth2Client
    (authorization code, PKCE, refresh, invalid_grant read as "consent again"), OAuthToken,
    TokenStoreInterface, InMemoryTokenStore, AccessTokenInterface, StaticAccessToken,
    RefreshingAccessToken.
  - Declarative: a provider described by a definition file (Definition, Endpoint, Field,
    Pointer, Template, Credentials, DeclarativeClient), DeclarativeProvider for holidays,
    DeclarativeFactory (`fromFile()`, `installed()`), resources/declarative-provider.schema.json.
  - Testing: FixedProvider and its factory, RecordedHttpClient, BareTestCase,
    DeclaredBareTestCase (a package of data checked bare).
  - The harness (docker, the image the other families use), the bare script, the manual in docs/.
