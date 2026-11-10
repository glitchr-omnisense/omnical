<?php

namespace Omnical\Tests;

use Omnical\Exception\InvalidArgumentException;
use Omnical\Model\Attendee;
use Omnical\Model\AttendeeRole;
use Omnical\Model\BusySlot;
use Omnical\Model\Calendar;
use Omnical\Model\Event;
use Omnical\Model\EventStatus;
use Omnical\Model\FreeBusy;
use Omnical\Model\FreeBusyStatus;
use Omnical\Model\Holiday;
use Omnical\Model\HolidayQuery;
use Omnical\Model\HolidayType;
use Omnical\Model\ParticipationStatus;
use Omnical\Model\Recurrence;
use Omnical\Model\Transparency;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testAnEventKeepsItsZoneAndAnAllDayEventHoldsDates(): void
    {
        $event = new Event('Consultation', new \DateTimeImmutable('2026-11-16T09:00', new \DateTimeZone('Europe/Paris')), new \DateTimeImmutable('2026-11-16T09:45', new \DateTimeZone('Europe/Paris')));
        self::assertSame('Europe/Paris', $event->timeZone);
        self::assertSame(2700, $event->duration());
        self::assertTrue($event->busy());
        self::assertTrue($event->overlaps(new \DateTimeImmutable('2026-11-16T08:30:00Z'), new \DateTimeImmutable('2026-11-16T08:31:00Z')), '09:30 in Paris is 08:30 UTC');

        $offset = new Event('Call', new \DateTimeImmutable('2026-11-16T09:00+09:00'), new \DateTimeImmutable('2026-11-16T10:00+09:00'));
        self::assertNull($offset->timeZone, 'an offset is no IANA zone');
        self::assertSame('Asia/Tokyo', (new Event('Call', new \DateTimeImmutable('2026-11-16T09:00+09:00'), new \DateTimeImmutable('2026-11-16T10:00+09:00'), timeZone: 'Asia/Tokyo'))->timeZone);

        $closed = new Event('Fermeture', new \DateTimeImmutable('2026-12-24T15:00'), new \DateTimeImmutable('2026-12-27T08:00'), allDay: true, timeZone: 'Europe/Paris');
        self::assertSame(['2026-12-24T00:00:00+01:00', '2026-12-27T00:00:00+01:00'], [$closed->start->format(\DATE_ATOM), $closed->end->format(\DATE_ATOM)]);

        $this->expectException(InvalidArgumentException::class);
        new Event('Nothing', new \DateTimeImmutable('2026-12-24'), new \DateTimeImmutable('2026-12-24'), allDay: true);
    }

    public function testStoredAndMovedKeepEverythingElse(): void
    {
        $event = new Event('RDV', new \DateTimeImmutable('2026-11-16T09:00Z'), new \DateTimeImmutable('2026-11-16T10:00Z'), calendarId: 'c', attendees: [new Attendee('MAILTO:Anna@Example.org', 'Anna')], status: EventStatus::Tentative, transparency: Transparency::Transparent);
        $stored = $event->stored('e1', 'c', '"etag-1"', 'uid-1');
        self::assertSame(['e1', 'c', '"etag-1"', 'uid-1', EventStatus::Tentative], [$stored->id, $stored->calendarId, $stored->etag, $stored->uid, $stored->status]);
        self::assertFalse($stored->busy(), 'transparent');
        self::assertSame('anna@example.org', $stored->attendee('mailto:anna@example.org')?->email);
        $moved = $stored->moved(new \DateTimeImmutable('2026-11-17T09:00Z'), new \DateTimeImmutable('2026-11-17T09:30Z'));
        self::assertSame(['e1', '"etag-1"', 1800], [$moved->id, $moved->etag, $moved->duration()]);
    }

    public function testARecurrenceIsKeptAsDataAndReadBack(): void
    {
        $paris = new \DateTimeZone('Europe/Paris');
        $recurrence = Recurrence::fromLines([
            'RRULE:freq=weekly;byday=MO,WE;until=20261231T225959Z',
            'EXDATE;TZID=Europe/Paris:20261123T090000,20261125T090000',
            'RDATE;VALUE=DATE:20261205',
        ], $paris);
        self::assertNotNull($recurrence);
        self::assertSame(['FREQ=WEEKLY;BYDAY=MO,WE;UNTIL=20261231T225959Z'], $recurrence->rules);
        self::assertSame(['WEEKLY', 1, null, ['MO', 'WE'], true], [$recurrence->frequency(), $recurrence->interval(), $recurrence->count(), $recurrence->days(), $recurrence->ends()]);
        self::assertSame('2026-12-31T22:59:59+00:00', $recurrence->until()?->format(\DATE_ATOM));
        self::assertSame(['2026-11-23T08:00:00Z', '2026-11-25T08:00:00Z'], array_map(static fn (\DateTimeImmutable $d) => $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'), $recurrence->exdates));
        self::assertSame([
            'RRULE:FREQ=WEEKLY;BYDAY=MO,WE;UNTIL=20261231T225959Z',
            'RDATE;TZID=Europe/Paris:20261205T000000',
            'EXDATE;TZID=Europe/Paris:20261123T090000,20261125T090000',
        ], $recurrence->toLines('Europe/Paris'));
        self::assertSame(['RRULE:FREQ=WEEKLY;BYDAY=MO,WE;UNTIL=20261231T225959Z', 'RDATE:20261204T230000Z', 'EXDATE:20261123T080000Z,20261125T080000Z'], $recurrence->toLines());
        self::assertNull(Recurrence::fromLines(['X-NOTHING:1']));
        self::assertFalse(Recurrence::rule('RRULE:FREQ=DAILY;INTERVAL=2')->ends());

        $this->expectException(InvalidArgumentException::class);
        Recurrence::rule('BYDAY=MO');
    }

    public function testAttendeesAndStatusesAreReadInEveryProvidersWords(): void
    {
        self::assertSame(ParticipationStatus::Tentative, ParticipationStatus::read('tentativelyAccepted'));
        self::assertSame(ParticipationStatus::NeedsAction, ParticipationStatus::read('NEEDS-ACTION'));
        self::assertSame(ParticipationStatus::Accepted, ParticipationStatus::read('accepted'));
        self::assertSame('NEEDS-ACTION', ParticipationStatus::NeedsAction->ical());
        self::assertSame(AttendeeRole::Optional, AttendeeRole::read('OPT-PARTICIPANT'));
        self::assertSame('CHAIR', AttendeeRole::Chair->ical());
        self::assertSame(EventStatus::Cancelled, EventStatus::read('CANCELED'));
        self::assertSame([FreeBusyStatus::Free, FreeBusyStatus::Tentative, FreeBusyStatus::OutOfOffice, FreeBusyStatus::Busy, FreeBusyStatus::Unavailable], array_map(FreeBusyStatus::read(...), ['workingElsewhere', 'BUSY-TENTATIVE', 'oof', 'unknown', 'BUSY-UNAVAILABLE']));
        self::assertSame(['#A1B2C3', '#AABBCC', null], [Calendar::color('#a1b2c3ff'), Calendar::color('abc'), Calendar::color('blue')]);
    }

    public function testFreeSpansAreWhatNoCalendarTakes(): void
    {
        $at = static fn (string $t) => new \DateTimeImmutable('2026-11-16T'.$t.':00Z');
        $one = new FreeBusy('a', [new BusySlot($at('13:00'), $at('14:00')), new BusySlot($at('09:00'), $at('10:30'), FreeBusyStatus::Tentative), new BusySlot($at('11:00'), $at('11:30'), FreeBusyStatus::Free)]);
        $two = new FreeBusy('b', [new BusySlot($at('10:00'), $at('11:15'))]);
        self::assertCount(2, $one->slots, 'free spans given by a provider are not busy time');
        self::assertSame('09:00', $one->slots[0]->start->format('H:i'), 'sorted');
        self::assertTrue($one->busy($at('09:30'), $at('09:45')));
        self::assertFalse($one->busy($at('11:00'), $at('12:00')));
        self::assertSame(['08:00-09:00', '10:30-13:00', '14:00-18:00'], self::spans($one->free($at('08:00'), $at('18:00'))));
        self::assertSame(['08:00-09:00', '11:15-13:00', '14:00-18:00'], self::spans(FreeBusy::common([$one, $two], $at('08:00'), $at('18:00'))));
        self::assertSame(['11:15-13:00', '14:00-18:00'], self::spans(FreeBusy::common([$one, $two], $at('08:00'), $at('18:00'), 5400)));
    }

    public function testHolidaysAnswerAQueryByDaysRegionAndType(): void
    {
        $holidays = [
            new Holiday('Noël', new \DateTimeImmutable('2026-12-25'), country: 'fr'),
            new Holiday('Saint-Étienne', new \DateTimeImmutable('2026-12-26'), country: 'FR', nationwide: false, regions: ['fr-57', 'FR-67', 'FR-68']),
            new Holiday('Vacances de Noël', new \DateTimeImmutable('2026-12-19'), new \DateTimeImmutable('2027-01-03'), 'FR', false, ['FR-A'], [HolidayType::School]),
            new Holiday('Noël', new \DateTimeImmutable('2026-12-25'), country: 'FR'),
        ];
        self::assertSame(['FR', ['FR-57', 'FR-67', 'FR-68'], 16], [$holidays[0]->country, $holidays[1]->regions, $holidays[2]->days()]);
        self::assertTrue($holidays[2]->covers(new \DateTimeImmutable('2027-01-02T23:00', new \DateTimeZone('Pacific/Auckland'))));

        self::assertSame(['Noël', 'Saint-Étienne'], self::names(HolidayQuery::year('FR', 2026)->filter($holidays)), 'every region, no school holidays, Noël once');
        self::assertSame(['Noël', 'Saint-Étienne'], self::names(HolidayQuery::year('FR', 2026, 'FR-67')->filter($holidays)));
        self::assertSame(['Noël'], self::names(HolidayQuery::year('FR', 2026, 'FR-75')->filter($holidays)));
        self::assertSame(['Vacances de Noël'], self::names(new HolidayQuery('FR', new \DateTimeImmutable('2027-01-01'), new \DateTimeImmutable('2027-01-31'), types: [HolidayType::School])->filter($holidays)), 'a run of days that started the year before');
        self::assertFalse($holidays[1]->holdsIn(null));
        self::assertTrue((new Holiday('Fronleichnam', new \DateTimeImmutable('2027-05-27'), country: 'DE', nationwide: false, regions: ['DE-BY']))->holdsIn('DE-BY-A'));

        $this->expectException(InvalidArgumentException::class);
        HolidayQuery::year('FR', 2026, 'DE-BY');
    }

    /**
     * @param list<BusySlot> $slots
     *
     * @return list<string>
     */
    private static function spans(array $slots): array
    {
        return array_map(static fn (BusySlot $s) => $s->start->format('H:i').'-'.$s->end->format('H:i'), $slots);
    }

    /**
     * @param list<Holiday> $holidays
     *
     * @return list<string>
     */
    private static function names(array $holidays): array
    {
        return array_map(static fn (Holiday $h) => $h->name, $holidays);
    }
}
