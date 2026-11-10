<?php

namespace Omnical\Auth;

/** Tokens for the length of the process: a test, a script, a command run once. */
final class InMemoryTokenStore implements TokenStoreInterface
{
    /** @param array<string, OAuthToken> $tokens */
    public function __construct(private array $tokens = [])
    {
    }

    public function get(string $key): ?OAuthToken
    {
        return $this->tokens[$key] ?? null;
    }

    public function save(string $key, OAuthToken $token): void
    {
        $this->tokens[$key] = $token;
    }

    public function delete(string $key): void
    {
        unset($this->tokens[$key]);
    }
}
