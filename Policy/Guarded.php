<?php

namespace Omnical\Policy;

use Omnical\Capabilities;
use Omnical\ProviderInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider behind its guard: what every Guarded* shares - its name, title
 * and capabilities as they are, the guard, the provider itself (inner()).
 *
 * @template P of ProviderInterface
 */
abstract class Guarded implements ProviderInterface
{
    /** @param P $provider */
    public function __construct(protected readonly ProviderInterface $provider, protected readonly PolicyGuard $guard)
    {
    }

    public function getName(): string
    {
        return $this->provider->getName();
    }

    public function getTitle(): string
    {
        return $this->provider->getTitle();
    }

    public function capabilities(): Capabilities
    {
        return $this->provider->capabilities();
    }

    /** @return P the provider, unguarded */
    public function inner(): ProviderInterface
    {
        return $this->provider;
    }

    public function guard(): PolicyGuard
    {
        return $this->guard;
    }
}
