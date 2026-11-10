<?php

namespace Omnical\Model;

use Omnical\Exception\InvalidArgumentException;

/**
 * A calendar a provider holds: its identifier there (Google's calendar id,
 * Graph's, a CalDAV collection's path), its name, its time zone (IANA), its
 * colour (#RRGGBB), whether the user may write in it, whether it is the
 * user's primary one; $version changes whenever anything in it does (a
 * CalDAV ctag, a sync token) when the provider gives one.
 */
final readonly class Calendar
{
    public ?string $color;

    public function __construct(
        public string $id,
        public string $name,
        public string $source,
        public ?string $timeZone = null,
        public ?string $description = null,
        ?string $color = null,
        public bool $readOnly = false,
        public bool $primary = false,
        public ?string $owner = null,
        public ?string $version = null,
    ) {
        if ('' === trim($id)) {
            throw new InvalidArgumentException('A calendar needs its identifier.');
        }
        if (null !== $timeZone) {
            Zone::check($timeZone);
        }
        $this->color = self::color($color);
    }

    /** "#a1b2c3", "A1B2C3", "#A1B2C3FF" (Apple's, with its alpha) as "#A1B2C3"; null when unreadable. */
    public static function color(?string $color): ?string
    {
        $hex = ltrim(trim((string) $color), '#');
        if (3 === \strlen($hex) && ctype_xdigit($hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return \in_array(\strlen($hex), [6, 8], true) && ctype_xdigit($hex) ? '#'.strtoupper(substr($hex, 0, 6)) : null;
    }
}
