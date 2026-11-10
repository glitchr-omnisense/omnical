# Installation

```
composer require omnisense/omnical omnical/openholidays
```

The core requires `omnisense/contracts` and `symfony/http-client-contracts` (interfaces). Each
provider package requires what it calls (`symfony/http-client`, and sabre/vobject and sabre/xml
for CalDAV) and takes an HTTP client from outside.

## Plain PHP

```php
use Omnical\Declarative\DeclarativeFactory;
use Omnical\Model\HolidayQuery;
use Omnical\Registry;
use Symfony\Component\HttpClient\HttpClient;

require 'vendor/autoload.php';

$http = HttpClient::create();
$registry = new Registry(DeclarativeFactory::installed($http), [
    'holidays' => ['factory' => 'openholidays', 'options' => ['user_agent' => 'MySite/1.0 (https://example.org; contact@example.org)']],
]);

foreach ($registry->holidays('holidays')->holidays(HolidayQuery::year('DE', 2027, 'DE-BY', 'de')) as $holiday) {
    echo $holiday->first->format('Y-m-d'), ' ', $holiday->name, $holiday->nationwide ? '' : ' ('.implode(', ', $holiday->regions).')', "\n";
}
```

A calendar provider is the same, with its token store and secrets:

```php
use Omnical\Auth\EnvSecrets;
use Omnical\Model\EventQuery;

$registry = new Registry([new GoogleFactory($http, $tokens, new EnvSecrets())], [
    'agenda' => ['factory' => 'google', 'options' => ['client_id' => '…', 'client_secret' => 'GOOGLE_CLIENT_SECRET', 'token' => 'user:42:google']],
]);
$events = $registry->calendarEvents('agenda', 'user-42')->events(new EventQuery('primary', new DateTimeImmutable('monday'), new DateTimeImmutable('saturday')));
```

`$tokens` is the application's `TokenStoreInterface` (its database); see [auth.md](auth.md).

## Configuration

Each entry of the registry's configuration: `factory` (the provider's name), `options` (its
own), `policy` (a tightening of its usage policy), `usage` (what the site is: `commercial`,
`open_source`, `contract`). The third argument is the `RateLimiterInterface` the calls count in:
an in-memory one by default, a shared one (omnibridge/symfony over symfony/rate-limiter) when the
application runs in many processes.
