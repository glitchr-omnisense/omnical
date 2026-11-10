<?php

namespace Omnical\Model;

use Omnical\Exception\InvalidArgumentException;

/** A span of time and how it is taken ($end excluded); FreeBusy::free() gives the Free ones. */
final readonly class BusySlot
{
    public \DateTimeImmutable $start;

    public \DateTimeImmutable $end;

    public function __construct(\DateTimeInterface $start, \DateTimeInterface $end, public FreeBusyStatus $status = FreeBusyStatus::Busy)
    {
        $this->start = \DateTimeImmutable::createFromInterface($start);
        $this->end = \DateTimeImmutable::createFromInterface($end);
        if ($this->end < $this->start) {
            throw new InvalidArgumentException('A busy span ends before it starts.');
        }
    }

    public function duration(): int
    {
        return $this->end->getTimestamp() - $this->start->getTimestamp();
    }

    public function overlaps(\DateTimeInterface $from, \DateTimeInterface $to): bool
    {
        return $this->start < $to && $this->end > $from;
    }
}
