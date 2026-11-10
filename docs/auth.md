# Secrets and OAuth

## Secrets by reference

Options never hold a secret: they name it. `Auth\SecretsInterface::secret($reference)` reads it
at the moment of the call.

- `EnvSecrets` (the default): `"GOOGLE_CLIENT_SECRET"` or `"env:GOOGLE_CLIENT_SECRET"` is that
  environment variable (getenv, `$_ENV`, `$_SERVER`).
- `ArraySecrets`: a map (tests, or what the host read from its vault).
- a host's own: Symfony's secrets vault, a key store.

A missing secret is an `InvalidConfigException` that quotes the reference only when it looks
like a variable's name: a value pasted by mistake into an option is never printed. `ArraySecrets`
and `OAuthToken` hide their values from `var_dump()` and `print_r()`.

## OAuth 2.0

`Auth\OAuth2Client` is the authorization code flow (RFC 6749) with PKCE (RFC 7636). Each
provider package builds it with its endpoints (`GoogleFactory::oauth()`,
`MicrosoftFactory::oauth()`):

```php
[$verifier, $challenge] = OAuth2Client::pkce();
$_SESSION['oauth'] = [$state = bin2hex(random_bytes(16)), $verifier];
header('Location: '.$oauth->authorizationUrl($redirect, $scopes, $state, $challenge, ['access_type' => 'offline', 'prompt' => 'consent']));

// on $redirect, after checking $_GET['state']:
$tokens->save('user:42:google', $oauth->exchange($_GET['code'], $redirect, $_SESSION['oauth'][1]));
```

- `TokenStoreInterface` is the application's (its database, encrypted): `get()`, `save()`,
  `delete()` by key. `InMemoryTokenStore` for a script or a test.
- `RefreshingAccessToken` gives the provider its bearer token: refreshed when it expires (within
  60 s), saved at once (Microsoft's refresh tokens replace themselves on each use; Google keeps
  the old one, and `OAuthToken::fromResponse()` keeps it then); refreshed once more when the
  provider answers 401.
- `StaticAccessToken`: a token by reference, as it is (a service account's, minted elsewhere).
- A refused refresh (`invalid_grant`), a token never stored, a second 401: an
  `AuthenticationException`. The user has to sign in and consent again.
