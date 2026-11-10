<?php

namespace Omnical\Exception;

/**
 * The provider refused the credentials: a token expired and could not be
 * refreshed, a refresh token revoked (invalid_grant), no token stored yet.
 * The user has to sign in and consent again.
 */
final class AuthenticationException extends ProviderException
{
    public static function consentNeeded(string $provider, string $why): self
    {
        return new self(\sprintf('%s: %s The user has to sign in and consent again.', $provider, $why), 401);
    }
}
