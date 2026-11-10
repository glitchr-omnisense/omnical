<?php

namespace Omnical\Model;

/** An attendee's role, iCalendar's ROLE (RFC 5545 §3.2.16). */
enum AttendeeRole: string
{
    case Required = 'required';
    case Optional = 'optional';
    case Chair = 'chair';
    case NonParticipant = 'non-participant';

    /** "REQ-PARTICIPANT", "OPT-PARTICIPANT", "CHAIR", "NON-PARTICIPANT", "optional"...; Required when unknown. */
    public static function read(?string $value): self
    {
        return match (strtolower(trim((string) $value))) {
            'opt-participant', 'optional' => self::Optional,
            'chair' => self::Chair,
            'non-participant' => self::NonParticipant,
            default => self::Required,
        };
    }

    public function ical(): string
    {
        return match ($this) {
            self::Required => 'REQ-PARTICIPANT',
            self::Optional => 'OPT-PARTICIPANT',
            self::Chair => 'CHAIR',
            self::NonParticipant => 'NON-PARTICIPANT',
        };
    }
}
