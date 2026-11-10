<?php

namespace Omnical\Tests;

use Omnical\Capabilities;
use Omnical\Exception\ConflictException;
use Omnical\Exception\InvalidConfigException;
use Omnical\Exception\NotSupportedException;
use Omnical\Exception\OmnicalException;
use Omnical\Exception\UsageLimitReachedException;
use Omnical\Model\Calendar;
use Omnical\Model\Event;
use Omnical\Model\EventQuery;
use Omnical\Model\FreeBusyQuery;
use Omnical\Model\Holiday;
use Omnical\Model\HolidayQuery;
use Omnical\Policy\GuardedCalendarEvents;
use Omnical\Registry;
use Omnical\Testing\FixedProvider;
use Omnical\Testing\FixedProviderFactory;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    public function testEveryContractIsGuardedAndCountedPerOperation(): void
    {
        $registry = new Registry([self::fixed()], ['office' => ['factory' => 'fixed', 'policy' => ['rateLimits' => [['max' => 2, 'window' => 'minute', 'operations' => ['events', 'create']]]]]]);

        $calendar = $registry->calendarEvents('office');
        self::assertInstanceOf(GuardedCalendarEvents::class, $calendar);
        self::assertInstanceOf(FixedProvider::class, $calendar->inner());
        self::assertSame(['calendar-events', 'free-busy', 'holidays'], $registry->get('office')->capabilities()->contracts());
        $calendar->calendars();
        $calendar->create(new Event('A', new \DateTimeImmutable('2026-11-16T09:00Z'), new \DateTimeImmutable('2026-11-16T10:00Z'), calendarId: 'c'));
        self::assertCount(1, $calendar->events(new EventQuery('c', new \DateTimeImmutable('2026-11-16'), new \DateTimeImmutable('2026-11-17'))));
        try {
            $calendar->events(new EventQuery('c', new \DateTimeImmutable('2026-11-16'), new \DateTimeImmutable('2026-11-17')));
            self::fail('A third counted call went through.');
        } catch (OmnicalException $e) {
            self::assertInstanceOf(UsageLimitReachedException::class, $e);
            self::assertSame('fixed\'s usage policy allows 2 a minute for create, events: reached, try again in 60 s.', $e->getMessage());
        }
        self::assertNull($registry->guard('office')->remaining('calendars'), 'calendars are not counted');
        self::assertCount(1, $registry->freeBusy('office')->freeBusy(new FreeBusyQuery(['c'], new \DateTimeImmutable('2026-11-16'), new \DateTimeImmutable('2026-11-17'))));
    }

    public function testALimitPerUserCountsEachUserApart(): void
    {
        $registry = new Registry([self::fixed()], ['office' => ['factory' => 'fixed', 'policy' => ['rateLimits' => [['max' => 1, 'window' => 'minute', 'scope' => 'user']]]]]);

        $registry->calendarEvents('office', 'user-1')->calendars();
        $registry->calendarEvents('office', 'user-2')->calendars();
        $this->expectException(UsageLimitReachedException::class);
        $registry->calendarEvents('office', 'user-1')->calendars();
    }

    public function testWritesHonourTheEtag(): void
    {
        $calendar = (new Registry([self::fixed()], ['office' => ['factory' => 'fixed']]))->calendarEvents('office');
        $created = $calendar->create(new Event('A', new \DateTimeImmutable('2026-11-16T09:00Z'), new \DateTimeImmutable('2026-11-16T10:00Z'), calendarId: 'c'));
        $updated = $calendar->update($created->moved(new \DateTimeImmutable('2026-11-16T11:00Z'), new \DateTimeImmutable('2026-11-16T12:00Z')));
        self::assertSame('2', $updated->etag);
        try {
            $calendar->update($created);
            self::fail('A stale etag was written over.');
        } catch (ConflictException $e) {
            self::assertSame(412, $e->status());
        }
        $calendar->delete('c', (string) $created->id, '2');
        self::assertNull($calendar->event('c', (string) $created->id));
    }

    public function testProvidersByContractAndByCountry(): void
    {
        $registry = new Registry([self::fixed(), new FixedProviderFactory(name: 'empty')], ['office' => ['factory' => 'fixed'], 'none' => ['factory' => 'empty']]);

        self::assertSame(['office', 'none'], $registry->names(Capabilities::HOLIDAYS));
        self::assertSame(['office', 'none'], $registry->covering('FR'), 'office names France, none names no country');
        self::assertSame(['none'], $registry->covering('JP'));
        self::assertSame(['Noël'], array_map(static fn (Holiday $h) => $h->name, $registry->holidays('office')->holidays(HolidayQuery::year('FR', 2026))));
    }

    public function testAProviderWithoutTheContractOrConfigurationIsSaidSo(): void
    {
        $registry = new Registry([self::fixed()], ['office' => ['factory' => 'fixed'], 'lost' => ['factory' => 'nowhere']]);
        try {
            $registry->get('lost');
            self::fail('An unknown factory built a provider.');
        } catch (InvalidConfigException $e) {
            self::assertSame('No "nowhere" factory for the "lost" provider; installed: fixed.', $e->getMessage());
        }
        $this->expectException(InvalidConfigException::class);
        $registry->get('missing');
    }

    public function testNotSupportedNamesTheProvider(): void
    {
        $e = NotSupportedException::capability(new \stdClass(), 'holidays', 'read calendars');
        self::assertSame('The "holidays" provider (stdClass) does not read calendars.', $e->getMessage());
    }

    private static function fixed(): FixedProviderFactory
    {
        return new FixedProviderFactory([new Calendar('c', 'Cabinet', 'fixed')], [], [new Holiday('Noël', new \DateTimeImmutable('2026-12-25'), country: 'FR')]);
    }
}
