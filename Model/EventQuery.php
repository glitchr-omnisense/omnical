<?php

namespace Omnical\Model;

use Omnical\Exception\InvalidArgumentException;

/**
 * The events of a calendar that take some of the time between $from and
 * $to ($to excluded): each occurrence of a series on its own when $expand
 * (the default), the series themselves otherwise; at most $limit, the
 * earliest first; $search narrows them to words of their title,
 * description, location or attendees where the provider searches.
 */
final readonly class EventQuery
{
    public \DateTimeImmutable $from;

    public \DateTimeImmutable $to;

    public function __construct(
        public string $calendarId,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
        public bool $expand = true,
        public int $limit = 250,
        public ?string $search = null,
        public ?string $timeZone = null,
    ) {
        $this->from = \DateTimeImmutable::createFromInterface($from);
        $this->to = \DateTimeImmutable::createFromInterface($to);
        if ('' === trim($calendarId)) {
            throw new InvalidArgumentException('An event query needs its calendar.');
        }
        if ($this->to <= $this->from) {
            throw new InvalidArgumentException('An event query needs its end after its start.');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('An event query asks for one event at least.');
        }
        if (null !== $timeZone) {
            Zone::check($timeZone);
        }
    }
}
