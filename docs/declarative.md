# Declarative providers

A provider described as data, loaded at run time, with no PHP class of its own: the first such
mechanism in the omnisense ecosystem. `omnical/openholidays` and `omnical/nager` are made this
way; a site may write its own definition for an API of the same kind.

## A definition

JSON (`resources/declarative-provider.schema.json` describes it), or YAML when `symfony/yaml` is
installed. Unknown keys are refused: a misspelt field must not pass for one left out.

```json
{
    "name": "nager",
    "title": "Nager.Date",
    "family": "omnical",
    "capabilities": ["holidays"],
    "baseUri": "https://nagerholidays.com/api/v4",
    "auth": {"type": "none"},
    "headers": {"Accept": "application/json"},
    "describe": {"countries": [], "regions": true, "types": ["public", "bank", "school"]},
    "endpoints": {
        "holidays": {
            "path": "/Holidays/{country}/{year}",
            "split": "year",
            "map": {
                "date": "/date",
                "name": "/name",
                "language": {"const": "en"},
                "nationwide": "/nationalHoliday",
                "regions": {"pointer": "/subdivisionCodes", "transform": "list"},
                "types": {"pointer": "/holidayTypes", "transform": ["list", "lower"]}
            },
            "errors": {"404": "empty"}
        }
    },
    "policy": "policy.json"
}
```

- `auth`: `none`, `header` (`name`, `secret`), `query` (`name`, `secret`), `bearer` (`secret`),
  `basic` (`username`, `secret`). `secret` is a **reference** read through the factory's
  `SecretsInterface` at each call (an environment variable by default); the `secret` option names
  another one. OAuth is refused: consent and refresh need code.
- `endpoints`: each operation (here the contract, `holidays`) is one endpoint or a list.
  - `path`, `query`, `headers`, `body` are templates: `{country}`, `{from:Y-m-d}`,
    `{language|upper}`. A placeholder alone whose value is not given drops the parameter; one
    inside a path is required, URL-encoded.
  - `split: "year"` makes one call per calendar year of the span, `{year}` set, `{from}` and
    `{to}` cut to the year.
  - `tags` call an endpoint only when the family asks for one of them: omnical's holidays ask by
    holiday type, so OpenHolidays' `/SchoolHolidays` is called only for school holidays.
  - `list`: a JSON Pointer (RFC 6901) to the answer's items; `""` or `"/"` is the answer itself.
  - `map`: each field of the family's model. A pointer, a list of pointers (the first that holds
    a value), or an object: `pointer`, `each` (a list, a pointer in each item), `values` (the
    provider's words to the family's), `transform` (not, upper, lower, trim, string, int, bool,
    list, first, date), `default`, `const`. A pointer segment may pick an item of a list by a
    field: `/name[language={language}]/text` (the first item when no language is asked).
  - `errors`: a status read as no answer (`empty`), or an error (`fail`, the default). A 404 is
    always no answer; a 429 always the provider asking to wait (the Backoff, Retry-After).
- `policy`: the package's `policy.json`, beside the definition, or the policy itself. The
  package's `composer.json` names both: `"extra": {"omnical": {"provider": "provider.json"},
  "omnisense": {"policy": "policy.json"}}`, so that `DeclarativeFactory::installed()` and
  `vendor/bin/omnisense-policies` find them.

omnical's holidays read these fields: name and date (required), end, localName, language,
country, nationwide, regions, types, halfDay, id, comment. The query then filters what came back
(`HolidayQuery::filter()`).

```php
$factory = DeclarativeFactory::fromFile(__DIR__.'/config/holidays/my-api.json', $http, $secrets);
$factories = DeclarativeFactory::installed($http);   // every installed package naming a definition
```

A declared provider is held to everything a provider in code is: its policy guarded on every
call, the User-Agent its policy asks for required, its credentials kept out of every message,
its package's tests on recorded answers, its `BareTest` (`Testing\DeclaredBareTestCase`: the
definition loaded and asked in a process of its own, nothing loaded but the core).

## What fits, what does not

Fits: plain REST JSON reads - holidays here; elsewhere statistics series, entity lookups, photo
search, one-POST translation. Does not: OAuth consent and refresh, writes with conditions
(ETags), webhooks and signatures, XML or binary bodies (CalDAV is code), cursor pagination,
multi-step processes, errors sent as 200. That is why google, microsoft and caldav are code, and
why openholidays and nager are not.

## Generic and family parts

| Class | Part |
|---|---|
| `Definition`, `Endpoint`, `Field`, `Pointer`, `Template`, `Credentials`, `DeclarativeClient` | generic: they know no omnical model; `DeclarativeClient::call()` gives plain values by field |
| `DeclarativeProvider`, `DeclarativeFactory` | omnical's: the fields onto `Holiday`, the Capabilities from `describe`, the factory's options |
| `Testing\DeclaredBareTestCase`, `bin/declared-bare.php` | omnical's test support |

## Proposal: move the generic part to omnisense/contracts

It lives in omnical for now, the only family that uses it. Once a second family needs it
(omnistate's statistics, omnilab's lookups and omnilang's translation are the candidates named
in the API families brief, §7), the generic classes move to `Omnisense\Contracts\Declarative\`
unchanged but for two points, and omnical keeps thin subclasses under its names, as it does for
the HTTP client:

- they throw omnical's `InvalidConfigException`; in the kernel, the kernel's
  `Omnisense\Contracts\Exception\InvalidConfigException` (a family's subclass through a hook, as
  `Config::invalid()` does);
- `DeclarativeClient` reads an error's status from its code (omnical's `ProviderException` sets
  it); the kernel's `ProviderException::answered()` should set it too, or carry a `status()`.

Each family keeps its own provider and factory (its model's fields, its Capabilities), its
`resources/*.schema.json` part for `map` and `describe`, and its own bare test case. Two more
changes the kernel could take at the same time, seen while writing this family: a raw call in
`JsonClient` (any method, the status and headers back, an empty or non-JSON body: omnical's
`Http\RawClient` duplicated `send()` for want of it), and the URL that finally answered after
redirects (CalDAV resolves hrefs against it). Both are in the kernel since contracts 5bac28b
(`JsonClient::raw()`, `Http\Answer::$url`): `Http\RawClient` now calls it.
