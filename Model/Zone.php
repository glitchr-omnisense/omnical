<?php

namespace Omnical\Model;

use Omnical\Exception\InvalidArgumentException;

/** Time zones as the models keep them: IANA names ("Europe/Paris"), checked. */
final class Zone
{
    public static function check(string $name): \DateTimeZone
    {
        try {
            $zone = new \DateTimeZone($name);
        } catch (\Exception) {
            throw new InvalidArgumentException(\sprintf('"%s" is no time zone PHP knows (an IANA name: "Europe/Paris").', $name));
        }

        return $zone;
    }

    /** The IANA name of a moment's zone; null for an offset ("+01:00") or an abbreviation ("CET"). */
    public static function nameOf(\DateTimeInterface $moment): ?string
    {
        $name = $moment->getTimezone()->getName();

        return str_contains($name, '/') || 'UTC' === $name ? $name : null;
    }
}
