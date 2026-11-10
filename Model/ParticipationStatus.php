<?php

namespace Omnical\Model;

/** An attendee's answer, iCalendar's PARTSTAT (RFC 5545 §3.2.12). */
enum ParticipationStatus: string
{
    case NeedsAction = 'needs-action';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Tentative = 'tentative';
    case Delegated = 'delegated';

    /** "NEEDS-ACTION", "needsAction", "tentativelyAccepted", "notResponded"...; NeedsAction when unknown. */
    public static function read(?string $value): self
    {
        return match (strtolower(str_replace(['-', '_', ' '], '', (string) $value))) {
            'accepted', 'organizer' => self::Accepted,
            'declined' => self::Declined,
            'tentative', 'tentativelyaccepted' => self::Tentative,
            'delegated' => self::Delegated,
            default => self::NeedsAction,
        };
    }

    /** As iCalendar writes it: "NEEDS-ACTION". */
    public function ical(): string
    {
        return strtoupper($this->value);
    }
}
