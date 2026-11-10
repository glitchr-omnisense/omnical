<?php

namespace Omnical\Tests;

use Omnical\Http\Data;
use Omnical\Registry;
use Omnical\Testing\BareTestCase;

/**
 * omnisense/omnical with no framework: the harness's bare script reads and
 * writes a calendar's events, finds the free spans two calendars share and
 * a region's holidays, reads holidays through a provider declared as data,
 * makes a provider's JSON and raw calls and refreshes an OAuth token, in a
 * PHP process of its own - nothing may load from outside the package but
 * omnisense/contracts and Symfony's HTTP client contracts (interfaces only).
 */
final class BareTest extends BareTestCase
{
    protected static function entryClass(): string
    {
        return Registry::class;
    }

    protected static function requiredPackages(): array
    {
        return ['symfony/http-client-contracts'];
    }

    public function testCalendarsAndHolidaysByHandLoadNothingButOmnicalAndWhatItRequires(): void
    {
        $report = Data::of(self::php([__DIR__.'/../docker/harness/bin/bare']));

        self::assertSame(['calendar-events', 'free-busy', 'holidays'], $report->get('capabilities'));
        self::assertSame(['fixed-1', '1', 'Europe/Paris'], $report->get('created'));
        self::assertSame([['Rendez-vous', '09:00', null], ['Point hebdomadaire', '14:00', 'WEEKLY'], ['Consultation', '16:00', null]], $report->get('events'));
        self::assertSame(['08:00-09:00', '10:00-11:00', '12:30-14:00', '14:30-16:00', '16:45-18:00'], $report->get('free'));
        self::assertSame([['2026-11-11', 'Armistice 1918'], ['2026-12-26', 'Saint-Étienne']], $report->get('holidays'));
        self::assertSame([['10-03', 'Tag der Deutschen Einheit', [], true], ['10-31', 'Reformationstag', ['DE-BB'], true]], $report->get('declared'));
        self::assertSame(['Rendez-vous', 'calendar-bare/1.0'], $report->get('json'));
        self::assertNull($report->get('json-404'));
        self::assertSame(['Example answered 500 for GET /events/3. Internal error', 500], $report->get('json-error'));
        self::assertSame(204, $report->get('delete'));
        self::assertSame(['fresh-token', 'a-refresh-token', 5600], $report->get('oauth'));

        $symbols = $report->strings('loaded.symbols');
        self::assertContains('Omnical\Testing\FixedProvider', $symbols);
        self::assertContains('Omnical\Declarative\DeclarativeProvider', $symbols);
        self::assertSame([], self::foreign($symbols), 'no class of a framework, an HTTP client or an application');
        self::assertSame([], self::outside($report->strings('loaded.files'), $report->strings('loaded.eager')), 'no file but the package and what it requires');
    }
}
