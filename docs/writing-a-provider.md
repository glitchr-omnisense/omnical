# Writing a provider

First ask whether it can be **declared** ([declarative.md](declarative.md)): plain REST JSON
reads of holidays need no code. Otherwise:

- a package `omnical/<name>`, namespace `Omnical\<Name>\`, requiring omnisense/omnical,
  omnisense/contracts and what it calls (`symfony/http-client`), taking an HTTP client, a token
  store and secrets in its factory's constructor;
- a factory extending `Omnical\ProviderFactory` (`populate()`: name, title, required options,
  defaults; `build()`: the provider), its JSON calls through `Http\JsonClient`, the rest through
  `Http\RawClient`, both given `$config->backoff()`;
- the provider implementing the contracts it keeps and `DeclaresUsagePolicyInterface`, its
  `policy.json` beside it (`extra.omnisense.policy`), read from the provider's own pages, the
  day written down;
- secrets by reference, tokens through `AccessTokenInterface` (refresh once on a 401);
- recorded answers for every call, the request asserted (method, URL, query, headers, body); a
  `PolicyTest`, a `BareTest`; PHPStan at max with an empty baseline;
- a README that says, call by call, what is read and how, and what was verified and what was not.
