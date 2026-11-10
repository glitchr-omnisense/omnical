<?php

namespace Omnical\Auth;

use Omnical\Exception\AuthenticationException;

/**
 * The bearer token a provider sends: token() before each call, refresh()
 * once when the provider answered 401 to it.
 */
interface AccessTokenInterface
{
    /** @throws AuthenticationException when there is none to give */
    public function token(): string;

    /**
     * A new token, the one given refused.
     *
     * @throws AuthenticationException when none can be had: the user has to consent again
     */
    public function refresh(): string;
}
