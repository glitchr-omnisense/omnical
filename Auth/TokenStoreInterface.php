<?php

namespace Omnical\Auth;

/**
 * Where a user's OAuth tokens are kept, by key ("user:42:google"): the
 * application's database, a cache, an encrypted file. A refreshed token is
 * saved over the old one (Microsoft's refresh tokens replace themselves on
 * every use: the old one must not be used again).
 */
interface TokenStoreInterface
{
    public function get(string $key): ?OAuthToken;

    public function save(string $key, OAuthToken $token): void;

    public function delete(string $key): void;
}
