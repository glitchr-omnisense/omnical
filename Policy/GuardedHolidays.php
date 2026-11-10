<?php

namespace Omnical\Policy;

use Omnical\HolidaysInterface;
use Omnical\Model\HolidayQuery;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's holidays behind its guard: each call the operation "holidays".
 *
 * @extends Guarded<HolidaysInterface>
 */
final class GuardedHolidays extends Guarded implements HolidaysInterface
{
    /** @param array{key?: string, ip?: string, user?: string} $context */
    public function __construct(HolidaysInterface $provider, PolicyGuard $guard, private readonly array $context = [])
    {
        parent::__construct($provider, $guard);
    }

    public function holidays(HolidayQuery $query): array
    {
        return $this->guard->call('holidays', fn () => $this->provider->holidays($query), $this->context);
    }
}
