# The harness

`docker/compose.yml`: PHP 8.4 with Composer, the core with every provider (their checkouts
beside it when `OMNICAL_PLUGINS` is set, GitHub's 1.x otherwise), omnisense/contracts likewise
(`OMNISENSE_CONTRACTS`). The image is the one the other omni families use
(`docker tag omnisense/omnitravel-harness omnisense/omnical-harness`), on Docker's default
bridge: no network of its own.

```
cd docker
docker compose run --rm omnical test                       # every package's tests
docker compose run --rm omnical test --testsuite caldav    # one package's
docker compose run --rm omnical bare                       # plain PHP, and what it loaded
docker compose run --rm omnical live                       # OpenHolidays and Nager.Date, two calls each
docker compose run --rm omnical phpstan                    # every package at level max
docker compose down -v
```

The CalDAV provider's tests run against sabre/dav 4.7 in process (its CalDAV server on SQLite in
memory): the harness installs it as a test dependency (`docker/harness/plugins.php`).
