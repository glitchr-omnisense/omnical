<?php

namespace Omnical\Auth;

use Omnical\Exception\InvalidConfigException;

/**
 * Where credentials are read, by reference: a provider's options name a
 * secret ("GOOGLE_CLIENT_SECRET", "env:CALDAV_PASSWORD"), never hold it, and
 * the secret is read at the moment of the call. EnvSecrets reads the
 * environment; a host gives its own (Symfony's vault, a key store).
 */
interface SecretsInterface
{
    /**
     * The secret a reference names.
     *
     * @throws InvalidConfigException when there is none (the message never quotes a value)
     */
    public function secret(string $reference): string;
}
