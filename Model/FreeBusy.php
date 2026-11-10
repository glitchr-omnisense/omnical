<?php

namespace Omnical\Model;

/**
 * One calendar's busy time between two moments, as its provider says it:
 * the spans taken (sorted, each with how it is taken), or why the provider
 * could not tell ($error: not found, not shared).
 */
final readonly class FreeBusy
{
    /** @var list<BusySlot> */
    public array $slots;

    /** @param list<BusySlot> $slots */
    public function __construct(public string $calendar, array $slots, public ?string $error = null, public ?string $source = null)
    {
        usort($slots, static fn (BusySlot $a, BusySlot $b) => [$a->start, $a->end] <=> [$b->start, $b->end]);
        $this->slots = array_values(array_filter($slots, static fn (BusySlot $s) => $s->status->taken()));
    }

    /** Whether any of the time between $from and $to is taken. */
    public function busy(\DateTimeInterface $from, \DateTimeInterface $to): bool
    {
        foreach ($this->slots as $slot) {
            if ($slot->overlaps($from, $to)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The free spans between $from and $to, $minimum seconds long at least.
     *
     * @return list<BusySlot> each FreeBusyStatus::Free
     */
    public function free(\DateTimeInterface $from, \DateTimeInterface $to, int $minimum = 0): array
    {
        return self::common([$this], $from, $to, $minimum);
    }

    /**
     * The spans free in every calendar given: when everyone can meet.
     *
     * @param list<self> $calendars
     *
     * @return list<BusySlot>
     */
    public static function common(array $calendars, \DateTimeInterface $from, \DateTimeInterface $to, int $minimum = 0): array
    {
        $taken = [];
        foreach ($calendars as $calendar) {
            foreach ($calendar->slots as $slot) {
                if ($slot->overlaps($from, $to)) {
                    $taken[] = [max($slot->start->getTimestamp(), $from->getTimestamp()), min($slot->end->getTimestamp(), $to->getTimestamp())];
                }
            }
        }
        sort($taken);
        $free = [];
        $cursor = $from->getTimestamp();
        $zone = $from->getTimezone();
        foreach ([...$taken, [$to->getTimestamp(), $to->getTimestamp()]] as [$start, $end]) {
            if ($start > $cursor && $start - $cursor >= max(1, $minimum)) {
                $free[] = new BusySlot((new \DateTimeImmutable('@'.$cursor))->setTimezone($zone), (new \DateTimeImmutable('@'.$start))->setTimezone($zone), FreeBusyStatus::Free);
            }
            $cursor = max($cursor, $end);
        }

        return $free;
    }
}
