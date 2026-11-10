<?php

namespace Omnical\Model;

/**
 * How a span of time is taken, iCalendar's FBTYPE (RFC 5545 §3.2.9) with
 * the out-of-office calendars say: Busy (BUSY), Tentative (BUSY-TENTATIVE),
 * Unavailable (BUSY-UNAVAILABLE), OutOfOffice; Free for the spans in
 * between.
 */
enum FreeBusyStatus: string
{
    case Busy = 'busy';
    case Tentative = 'tentative';
    case Unavailable = 'unavailable';
    case OutOfOffice = 'out-of-office';
    case Free = 'free';

    /** "BUSY-TENTATIVE", "oof", "workingElsewhere" (free), "unknown" (busy, to be safe)... */
    public static function read(?string $value): self
    {
        return match (strtolower(str_replace(['_', ' '], '-', trim((string) $value)))) {
            'free', 'workingelsewhere', 'working-elsewhere' => self::Free,
            'busy-tentative', 'tentative' => self::Tentative,
            'busy-unavailable', 'unavailable' => self::Unavailable,
            'oof', 'out-of-office', 'outofoffice' => self::OutOfOffice,
            default => self::Busy,
        };
    }

    /** Whether the span is taken (anything but Free). */
    public function taken(): bool
    {
        return self::Free !== $this;
    }
}
