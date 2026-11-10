<?php

namespace Omnical\Model;

use Omnical\Exception\InvalidArgumentException;

/**
 * Someone asked to an event, or its organizer: an address ("mailto:" taken
 * off, in lower case), a name, the answer given, the role, whether it is a
 * room or a piece of equipment ($resource), whether it is the calendar's
 * own user ($self).
 */
final readonly class Attendee
{
    public string $email;

    public function __construct(
        string $email,
        public ?string $name = null,
        public ParticipationStatus $status = ParticipationStatus::NeedsAction,
        public AttendeeRole $role = AttendeeRole::Required,
        public bool $organizer = false,
        public bool $resource = false,
        public bool $self = false,
    ) {
        $email = strtolower(trim((string) preg_replace('~^mailto:~i', '', trim($email))));
        if ('' === $email) {
            throw new InvalidArgumentException('An attendee needs an address.');
        }
        $this->email = $email;
    }
}
