<?php

namespace Omnical\Auth;

use Omnical\Exception\AuthenticationException;

/**
 * A token read from the secrets by reference, as it is: a service account's
 * token minted elsewhere, a personal token. It cannot be refreshed.
 */
final class StaticAccessToken implements AccessTokenInterface
{
    public function __construct(private readonly SecretsInterface $secrets, private readonly string $reference, private readonly string $provider = 'The provider')
    {
    }

    public function token(): string
    {
        return $this->secrets->secret($this->reference);
    }

    public function refresh(): string
    {
        throw AuthenticationException::consentNeeded($this->provider, 'the access token given was refused, and a token given as it is cannot be refreshed.');
    }
}
