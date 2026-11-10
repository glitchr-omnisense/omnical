<?php

namespace Omnical\Auth;

use Omnical\Exception\AuthenticationException;

/**
 * A user's token from a store, refreshed through its OAuth client when it
 * expires (within $leeway seconds) or when the provider refused it, the new
 * one saved over the old one at once.
 */
final class RefreshingAccessToken implements AccessTokenInterface
{
    /** @var \Closure(): int */
    private readonly \Closure $now;

    /** @param ?\Closure(): int $now the clock (time() by default) */
    public function __construct(
        private readonly OAuth2Client $client,
        private readonly TokenStoreInterface $store,
        private readonly string $key,
        private readonly int $leeway = 60,
        ?\Closure $now = null,
    ) {
        $this->now = $now ?? time(...);
    }

    public function token(): string
    {
        $token = $this->stored();

        return $token->expired(($this->now)(), $this->leeway) ? $this->renew($token) : $token->accessToken;
    }

    public function refresh(): string
    {
        return $this->renew($this->stored());
    }

    private function stored(): OAuthToken
    {
        return $this->store->get($this->key) ?? throw AuthenticationException::consentNeeded($this->client->provider(), \sprintf('no token is stored under "%s" yet.', $this->key));
    }

    private function renew(OAuthToken $token): string
    {
        $fresh = $this->client->refresh($token);
        $this->store->save($this->key, $fresh);

        return $fresh->accessToken;
    }
}
