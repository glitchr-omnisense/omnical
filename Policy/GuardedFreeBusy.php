<?php

namespace Omnical\Policy;

use Omnical\FreeBusyInterface;
use Omnical\Model\FreeBusyQuery;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's free/busy behind its guard: each call the operation "freeBusy".
 *
 * @extends Guarded<FreeBusyInterface>
 */
final class GuardedFreeBusy extends Guarded implements FreeBusyInterface
{
    /** @param array{key?: string, ip?: string, user?: string} $context */
    public function __construct(FreeBusyInterface $provider, PolicyGuard $guard, private readonly array $context = [])
    {
        parent::__construct($provider, $guard);
    }

    public function freeBusy(FreeBusyQuery $query): array
    {
        return $this->guard->call('freeBusy', fn () => $this->provider->freeBusy($query), $this->context);
    }
}
