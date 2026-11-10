# Errors

Every exception implements `Omnical\Exception\OmnicalException` (and omnisense/contracts'
`ExceptionInterface`).

| Exception | When |
|---|---|
| `InvalidArgumentException` | a value no model can hold: an event ending before it starts, a rule without FREQ, a country that is no ISO code |
| `InvalidConfigException` | a provider asked for with options it cannot work with, a definition that does not hold, a secret not found |
| `NotSupportedException` | what a provider does not do: a contract it does not keep, a recurrence its API cannot say |
| `ProviderException` | the provider answered an error; `status()` is the HTTP status |
| `AuthenticationException` (a ProviderException, 401) | the token refused and not refreshable, a revoked grant: the user consents again |
| `ConflictException` (a ProviderException, 412) | the event changed since it was read (its ETag): read it again |
| `UnreachableException` | a network failure, a timeout |
| `UsageLimitReachedException` | a call refused before it was made: a limit of the policy, or the provider's wait (`retryAfter`) |

No message carries a credential: the query's keys, the headers' tokens, a body's secrets are
masked, and no trace keeps a call's arguments.
