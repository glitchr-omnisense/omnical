<?php

namespace Omnical\Auth;

/** Secrets from a map: a test's, or what a host read from its own vault. */
final class ArraySecrets implements SecretsInterface
{
    /** @param array<string, string> $secrets by reference */
    public function __construct(private readonly array $secrets)
    {
    }

    public function secret(string $reference): string
    {
        $value = $this->secrets[$reference] ?? $this->secrets[str_starts_with($reference, 'env:') ? substr($reference, 4) : $reference] ?? null;

        return null !== $value && '' !== $value ? $value : throw EnvSecrets::missing($reference);
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return array_map(static fn () => '***', $this->secrets);
    }
}
