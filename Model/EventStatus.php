<?php

namespace Omnical\Model;

/** An event's status, as iCalendar's STATUS says it (RFC 5545 §3.8.1.11). */
enum EventStatus: string
{
    case Confirmed = 'confirmed';
    case Tentative = 'tentative';
    case Cancelled = 'cancelled';

    /** "CONFIRMED", "confirmed", "canceled"...; Confirmed when unknown. */
    public static function read(?string $value): self
    {
        return match (strtolower(trim((string) $value))) {
            'tentative' => self::Tentative,
            'cancelled', 'canceled' => self::Cancelled,
            default => self::Confirmed,
        };
    }
}
