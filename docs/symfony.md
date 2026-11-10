# Symfony

omnical needs no bundle: its registry is a plain object. In a Symfony application, declare it as
a service built from the factories (each autowired with the application's HTTP client) and the
configuration, with the application's token store (a Doctrine repository implementing
`TokenStoreInterface`) and its secrets:

```yaml
services:
    Omnical\Auth\SecretsInterface: '@App\Calendar\VaultSecrets'
    Omnical\Auth\TokenStoreInterface: '@App\Repository\CalendarTokenRepository'
    Omnical\Registry:
        arguments:
            $factories: !tagged_iterator omnical.factory
            $config: '%app.calendars%'
            $limiter: '@App\Calendar\SharedRateLimiter'
```

`omnibridge/symfony` will wire it (the factories tagged, a `RateLimiterInterface` over
symfony/rate-limiter, the secrets vault as `SecretsInterface`) when a site needs it; not before
(the omni plan's rule 12).
