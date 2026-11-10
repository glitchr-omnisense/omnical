<?php

namespace Omnical\Model;

use Omnical\Exception\InvalidArgumentException;

/**
 * The busy time of calendars between $from and $to: calendars by the
 * provider's identifiers, or people by their address where the provider
 * answers for them (Google's calendars of a domain, Graph's schedules).
 */
final readonly class FreeBusyQuery
{
    public \DateTimeImmutable $from;

    public \DateTimeImmutable $to;

    /** @param non-empty-list<string> $calendars */
    public function __construct(public array $calendars, \DateTimeInterface $from, \DateTimeInterface $to, public ?string $timeZone = null)
    {
        $this->from = \DateTimeImmutable::createFromInterface($from);
        $this->to = \DateTimeImmutable::createFromInterface($to);
        if ($this->to <= $this->from) {
            throw new InvalidArgumentException('A free/busy query needs its end after its start.');
        }
        if ([] === array_filter($calendars, static fn (string $c) => '' !== trim($c))) {
            throw new InvalidArgumentException('A free/busy query needs a calendar or an address.');
        }
        if (null !== $timeZone) {
            Zone::check($timeZone);
        }
    }
}
