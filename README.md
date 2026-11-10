# omnisense/omnical

Calendars and public holidays under any PHP host, whoever keeps them: one family in three
contracts.

| Contract | What it answers |
|---|---|
| `CalendarEventsInterface` (`calendar-events`) | a user's calendars and their events, read and written: time zones, recurrence kept as RRULE data (each occurrence on its own when asked), attendees and their answers, ETags against lost updates |
| `FreeBusyInterface` (`free-busy`) | when calendars, or people, are busy; `FreeBusy::common()` gives the spans free in all of them |
| `HolidaysInterface` (`holidays`) | a country's public (and school) holidays, by region and type, in the language asked |

| Provider | Contracts | Written as | Key |
|---|---|---|---|
| [omnical/google](https://github.com/glitchr-omnisense/omnical-google) | calendar-events, free-busy - Google Calendar API v3 | code | OAuth 2.0 |
| [omnical/microsoft](https://github.com/glitchr-omnisense/omnical-microsoft) | calendar-events, free-busy - Microsoft Graph (Outlook, Microsoft 365) | code | OAuth 2.0 |
| [omnical/caldav](https://github.com/glitchr-omnisense/omnical-caldav) | calendar-events, free-busy - CalDAV (RFC 4791): iCloud, Fastmail, Nextcloud, any sabre/dav server | code | a password (an app password) or a token |
| [omnical/openholidays](https://github.com/glitchr-omnisense/omnical-openholidays) | holidays - 36 countries, public and school holidays, ODbL | **data** (`provider.json`) | none |
| [omnical/nager](https://github.com/glitchr-omnisense/omnical-nager) | holidays - 200+ countries; commercial use needs a sponsorship | **data** (`provider.json`) | none |

```php
use Omnical\Declarative\DeclarativeFactory;
use Omnical\Model\{EventQuery, FreeBusy, FreeBusyQuery, HolidayQuery};
use Omnical\Registry;

$calendars = new Registry([new GoogleFactory($http, $tokens), ...DeclarativeFactory::installed($http)], [
    'agenda' => ['factory' => 'google', 'options' => ['client_id' => '…apps.googleusercontent.com', 'client_secret' => 'GOOGLE_CLIENT_SECRET', 'token' => 'user:42:google']],
    'holidays' => ['factory' => 'openholidays', 'options' => ['user_agent' => 'MySite/1.0 (https://example.org; contact@example.org)']],
]);

$week = $calendars->calendarEvents('agenda', 'user-42')->events(new EventQuery('primary', $monday, $saturday));
$busy = $calendars->freeBusy('agenda', 'user-42')->freeBusy(new FreeBusyQuery(['primary', 'colleague@example.org'], $monday, $saturday));
$slots = FreeBusy::common($busy, $monday, $saturday, 3600);              // an hour free for both
$closed = $calendars->holidays('holidays')->holidays(HolidayQuery::year('FR', 2027, 'FR-GE-BR', 'fr'));
```

## A provider described as data

`omnical/openholidays` and `omnical/nager` hold no PHP: a definition file (base URI, auth by
reference, endpoints, how each answer's fields map onto `Holiday`, the policy beside it), loaded
at run time by `Declarative\DeclarativeFactory`. A site may write its own the same way.
[docs/declarative.md](docs/declarative.md) says what fits (plain REST JSON reads) and what does
not (OAuth consent, writes, webhooks, XML), and proposes the mechanism's move to
omnisense/contracts once a second family needs it.

## Usage policies, secrets, OAuth

Each provider declares its usage policy as data (`policy.json`: rate limits, rights, attribution,
requirements, where and when it was read), and every call through `calendarEvents()`,
`freeBusy()` or `holidays()` is held to it: checked before, counted after (per user where the
provider counts per user: pass the site's user to the accessor), refused with
`UsageLimitReachedException` once a limit is reached or while the provider asks to wait. A site
tightens a policy in its configuration (`policy`), says what it is (`usage`), never loosens it.

Secrets go by reference: options name a secret (`GOOGLE_CLIENT_SECRET`), `Auth\SecretsInterface`
reads it at the moment of the call (`EnvSecrets` by default, the host's vault otherwise). OAuth
2.0 is `Auth\OAuth2Client` (authorization code, PKCE, refresh) with a `TokenStoreInterface` the
application keeps; `RefreshingAccessToken` refreshes before expiry and once on a 401.
[docs/auth.md](docs/auth.md), [docs/policies.md](docs/policies.md).

`omnisense/omnical` requires `omnisense/contracts` (the kernel every omnisense family builds on)
and `symfony/http-client-contracts`, nothing else: no framework, no HTTP client of its own (each
provider package requires what it calls and takes a client from outside).

## Documentation

- [docs/index.md](docs/index.md): the manual's contents
- [docs/installation.md](docs/installation.md): plain PHP first
- [docs/models.md](docs/models.md), [docs/providers.md](docs/providers.md), [docs/declarative.md](docs/declarative.md)
- [docs/auth.md](docs/auth.md), [docs/policies.md](docs/policies.md), [docs/errors.md](docs/errors.md)
- [docs/testing.md](docs/testing.md), [docs/harness.md](docs/harness.md), [docs/writing-a-provider.md](docs/writing-a-provider.md), [docs/symfony.md](docs/symfony.md)

Boundaries: omnical reads and writes calendars and holidays; `omnibase/agenda` publishes a site's
own dates and ICS feeds; omnimeet opens the room an appointment leads to.

## Licence

MIT
