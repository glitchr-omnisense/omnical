<?php

namespace Omnical\Model;

use Omnical\Exception\InvalidArgumentException;

/**
 * How an event repeats, kept as iCalendar data (RFC 5545 §3.8.5): its RRULE
 * values ("FREQ=WEEKLY;BYDAY=MO,WE;UNTIL=20261231T225959Z"), its EXRULEs
 * where a provider still sends them, the dates added (RDATE) and taken out
 * (EXDATE). Nothing is expanded here: a provider asked for occurrences
 * (EventQuery::$expand) gives them, each an Event with $recurringEventId and
 * $originalStart; a provider that cannot say a rule its API does not know
 * throws NotSupportedException rather than write another one.
 *
 * Google's "recurrence" lines, CalDAV's properties and Graph's pattern (in
 * omnical/microsoft) are read into it and written back from it.
 */
final readonly class Recurrence
{
    private const FREQUENCIES = ['SECONDLY', 'MINUTELY', 'HOURLY', 'DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];

    /** @var list<string> */
    public array $rules;

    /** @var list<string> */
    public array $exrules;

    /**
     * @param list<string>             $rules   RRULE values, "RRULE:" taken off
     * @param list<\DateTimeImmutable> $rdates  the occurrences added
     * @param list<\DateTimeImmutable> $exdates the occurrences taken out (each its original start)
     * @param list<string>             $exrules EXRULE values (deprecated by RFC 5545, still sent by some)
     */
    public function __construct(
        array $rules,
        public array $rdates = [],
        public array $exdates = [],
        array $exrules = [],
    ) {
        $this->rules = array_map(self::normalise(...), $rules);
        $this->exrules = array_map(self::normalise(...), $exrules);
        if ([] === $this->rules && [] === $rdates) {
            throw new InvalidArgumentException('A recurrence needs a rule or dates.');
        }
    }

    /** @param list<\DateTimeImmutable> $exdates */
    public static function rule(string $rule, array $exdates = []): self
    {
        return new self([$rule], [], $exdates);
    }

    /**
     * iCalendar lines as Google's "recurrence" holds them: "RRULE:...",
     * "EXRULE:...", "RDATE;TZID=Europe/Paris:20261110T100000,20261117T100000",
     * "EXDATE;VALUE=DATE:20261224". A local time without TZID, and a date,
     * is read in $zone (the event's).
     *
     * @param list<string> $lines
     */
    public static function fromLines(array $lines, ?\DateTimeZone $zone = null): ?self
    {
        $rules = $exrules = $rdates = $exdates = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (!preg_match('~^([A-Z-]+)((?:;[^:]*)?):(.*)$~i', $line, $m)) {
                continue;
            }
            $name = strtoupper($m[1]);
            $params = [];
            foreach (array_filter(explode(';', $m[2])) as $param) {
                [$key, $value] = explode('=', $param, 2) + [1 => ''];
                $params[strtoupper($key)] = trim($value, '"');
            }
            match ($name) {
                'RRULE' => $rules[] = $m[3],
                'EXRULE' => $exrules[] = $m[3],
                'RDATE' => array_push($rdates, ...self::dates($m[3], $params, $zone)),
                'EXDATE' => array_push($exdates, ...self::dates($m[3], $params, $zone)),
                default => null,
            };
        }

        return [] === $rules && [] === $rdates ? null : new self($rules, $rdates, $exdates, $exrules);
    }

    /**
     * The lines again, dates written in the event's zone (TZID) when it has
     * one, as dates for an all-day event, in UTC otherwise.
     *
     * @return list<string>
     */
    public function toLines(?string $timeZone = null, bool $allDay = false): array
    {
        $lines = [...array_map(static fn (string $r) => 'RRULE:'.$r, $this->rules), ...array_map(static fn (string $r) => 'EXRULE:'.$r, $this->exrules)];
        foreach (['RDATE' => $this->rdates, 'EXDATE' => $this->exdates] as $name => $dates) {
            if ([] !== $dates) {
                $lines[] = $name.self::written($dates, $timeZone, $allDay);
            }
        }

        return $lines;
    }

    /**
     * A rule's parts, by name in upper case: ["FREQ" => "WEEKLY", "BYDAY" => "MO,WE"].
     *
     * @return array<string, string>
     */
    public function parts(int $rule = 0): array
    {
        $parts = [];
        foreach (explode(';', $this->rules[$rule] ?? '') as $part) {
            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $parts[strtoupper(trim($key))] = trim($value);
            }
        }

        return $parts;
    }

    /** "WEEKLY", "MONTHLY"...; null for a recurrence of dates only. */
    public function frequency(): ?string
    {
        return $this->parts()['FREQ'] ?? null;
    }

    public function interval(): int
    {
        return max(1, (int) ($this->parts()['INTERVAL'] ?? 1));
    }

    public function count(): ?int
    {
        $count = $this->parts()['COUNT'] ?? null;

        return null === $count ? null : (int) $count;
    }

    /** The rule's UNTIL, a date at midnight UTC or a moment in UTC. */
    public function until(): ?\DateTimeImmutable
    {
        $until = $this->parts()['UNTIL'] ?? null;

        return null === $until ? null : self::date($until, [], null);
    }

    /** @return list<string> BYDAY's days ("MO", "1MO", "-1FR") */
    public function days(): array
    {
        $days = $this->parts()['BYDAY'] ?? '';

        return '' === $days ? [] : array_map('trim', explode(',', strtoupper($days)));
    }

    /** Whether it ends (COUNT, UNTIL) or repeats for ever. */
    public function ends(): bool
    {
        $parts = $this->parts();

        return [] === $this->rules || isset($parts['COUNT']) || isset($parts['UNTIL']);
    }

    private static function normalise(string $rule): string
    {
        $rule = trim((string) preg_replace('~^(RRULE|EXRULE):~i', '', trim($rule)));
        $parts = [];
        foreach (array_filter(explode(';', $rule), static fn (string $p) => '' !== trim($p)) as $part) {
            if (!str_contains($part, '=')) {
                throw new InvalidArgumentException(\sprintf('"%s" is no recurrence rule part (NAME=value).', $part));
            }
            [$key, $value] = explode('=', $part, 2);
            $parts[] = strtoupper(trim($key)).'='.strtoupper(trim($value));
        }
        $freq = null;
        foreach ($parts as $part) {
            if (str_starts_with($part, 'FREQ=')) {
                $freq = strtoupper(substr($part, 5));
            }
        }
        if (null === $freq || !\in_array($freq, self::FREQUENCIES, true)) {
            throw new InvalidArgumentException(\sprintf('The recurrence rule "%s" needs a FREQ among %s.', $rule, implode(', ', self::FREQUENCIES)));
        }

        return implode(';', $parts);
    }

    /**
     * @param array<string, string> $params
     *
     * @return list<\DateTimeImmutable>
     */
    private static function dates(string $values, array $params, ?\DateTimeZone $zone): array
    {
        $dates = [];
        foreach (explode(',', $values) as $value) {
            $date = self::date(explode('/', trim($value))[0], $params, $zone);
            if (null !== $date) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    /** @param array<string, string> $params */
    private static function date(string $value, array $params, ?\DateTimeZone $zone): ?\DateTimeImmutable
    {
        $value = trim($value);
        if (preg_match('~^\d{8}$~', $value)) {
            return \DateTimeImmutable::createFromFormat('!Ymd', $value, $zone ?? new \DateTimeZone('UTC')) ?: null;
        }
        if (!preg_match('~^(\d{8}T\d{6})(Z?)$~i', $value, $m)) {
            return null;
        }
        $in = '' !== $m[2] ? new \DateTimeZone('UTC') : (isset($params['TZID']) ? self::zone($params['TZID']) : null) ?? $zone ?? new \DateTimeZone('UTC');

        return \DateTimeImmutable::createFromFormat('Ymd\THis', strtoupper($m[1]), $in) ?: null;
    }

    private static function zone(string $name): ?\DateTimeZone
    {
        try {
            return new \DateTimeZone($name);
        } catch (\Exception) {
            return null;
        }
    }

    /** @param list<\DateTimeImmutable> $dates */
    private static function written(array $dates, ?string $timeZone, bool $allDay): string
    {
        if ($allDay) {
            return ';VALUE=DATE:'.implode(',', array_map(static fn (\DateTimeImmutable $d) => $d->format('Ymd'), $dates));
        }
        if (null !== $timeZone && 'UTC' !== $timeZone) {
            $zone = Zone::check($timeZone);

            return ';TZID='.$timeZone.':'.implode(',', array_map(static fn (\DateTimeImmutable $d) => $d->setTimezone($zone)->format('Ymd\THis'), $dates));
        }

        return ':'.implode(',', array_map(static fn (\DateTimeImmutable $d) => $d->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z'), $dates));
    }
}
