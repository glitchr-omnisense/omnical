<?php

namespace Omnical;

/** Builds a provider from its options (a token's key, a secret's reference, a User-Agent...). */
interface ProviderFactoryInterface extends \Omnisense\Contracts\Provider\ProviderFactoryInterface
{
    /** The name providers are configured with: "google", "caldav", "openholidays"... */
    public function getName(): string;

    /** @param array<string, mixed> $options */
    public function create(array $options = []): ProviderInterface;
}
