# Usage policies

Every provider package ships `policy.json` (omnisense/contracts' schema) and names it in its
`composer.json` (`extra.omnisense.policy`). The registry wraps every call through a contract in
the provider's `PolicyGuard`: checked before, counted after, refused with
`Exception\UsageLimitReachedException` (its `retryAfter`) once a limit is reached or while the
provider asks to wait (a 429 and its `Retry-After`; Google's 403 `rateLimitExceeded` too).

Operations are the contracts' method names: `calendars`, `events`, `event`, `create`, `update`,
`delete`, `freeBusy`, `holidays`. A limit scoped per user counts the user given to the accessor:
`$registry->calendarEvents('agenda', $userId)`.

A site tightens a policy, never loosens it:

```php
'agenda' => [
    'factory' => 'google',
    'options' => [...],
    'policy' => ['rateLimits' => [['max' => 100, 'window' => 'minute', 'scope' => 'user']]],
    'usage' => ['commercial' => true],
],
'holidays' => ['factory' => 'nager', 'usage' => ['commercial' => true, 'contract' => true]],  // a sponsor
```

Without `contract`, a commercial site on Nager.Date is refused at once (`UsageNotPermitted`).
`$registry->policy($name)` and `policies()` give the policies as the site runs them: what a
"sources and terms" page lists. As read on 2026-11-10:

| Provider | Limits | Commercial | Attribution |
|---|---|---|---|
| google | 10,000/min project, 600/min user, 1,000,000/day | allowed | none (branding rules) |
| microsoft | 10,000 per 10 min per user (mailbox), 4 at a time | allowed | none |
| caldav | unknown: the server's own | unknown | none |
| openholidays | unknown (none stated) | allowed | required: ODbL |
| nager | none ("no rate limits") | by sponsorship | not stated |

## Writes sent once

The HTTP client a provider is given (the application's `http_client`, or a site's own) is kept
with its retries off where every call goes out, omnical's `Http\JsonClient`
(omnisense/contracts' `Http\NoRetry`): Symfony's `RetryableHttpClient`
(`framework.http_client`'s `retry_failed`) would send an event created, moved or deleted again on a 429, 502 or 503, and a
429 retried by the client never reaches the provider's wait. Nothing retries a call; the caller
decides. A `MockHttpClient` is kept as given (its `getRequestsCount()` still counts). It needs
omnisense/contracts 1568f2c or later; with an older one the client is kept as given.
