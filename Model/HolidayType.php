<?php

namespace Omnical\Model;

/**
 * What kind of day off: a public holiday (by law), a bank holiday (banks and
 * offices closed), a school holiday, an optional one (a choice left to
 * employers or employees), an observance (a named day, nobody off), a day
 * off for the authorities only.
 */
enum HolidayType: string
{
    case Public = 'public';
    case Bank = 'bank';
    case School = 'school';
    case Optional = 'optional';
    case Observance = 'observance';
    case Authorities = 'authorities';

    /** "Public", "bank", "BackToSchool" (an observance)...; Observance when unknown. */
    public static function read(?string $value): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? self::Observance;
    }
}
