<?php

namespace Omnical\Auth;

use Omnical\Exception\InvalidConfigException;

/**
 * Secrets from the environment: "NAME" or "env:NAME" is the variable NAME,
 * read from getenv(), $_ENV or $_SERVER at the moment it is asked.
 */
final class EnvSecrets implements SecretsInterface
{
    public function secret(string $reference): string
    {
        $name = str_starts_with($reference, 'env:') ? substr($reference, 4) : $reference;
        $value = getenv($name);
        if (!\is_string($value) || '' === $value) {
            $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
        }

        return \is_string($value) && '' !== $value ? $value : throw self::missing($reference);
    }

    /** No secret: the reference is quoted only when it looks like a variable's name, never like a value. */
    public static function missing(string $reference): InvalidConfigException
    {
        $name = str_starts_with($reference, 'env:') ? substr($reference, 4) : $reference;

        return new InvalidConfigException(preg_match('~^[A-Z][A-Z0-9_]{0,99}$~', $name)
            ? \sprintf('No secret "%s": set the environment variable, or give the provider\'s factory the secrets it is kept in.', $name)
            : 'No secret under the reference given: an option names a secret (an environment variable such as GOOGLE_CLIENT_SECRET), it does not hold it.');
    }
}
