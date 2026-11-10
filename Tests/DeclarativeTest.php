<?php

namespace Omnical\Tests;

use Omnical\Auth\ArraySecrets;
use Omnical\Declarative\DeclarativeFactory;
use Omnical\Declarative\DeclarativeProvider;
use Omnical\Declarative\Definition;
use Omnical\Declarative\Field;
use Omnical\Declarative\Pointer;
use Omnical\Declarative\Template;
use Omnical\Exception\InvalidConfigException;
use Omnical\Exception\ProviderException;
use Omnical\Exception\UsageLimitReachedException;
use Omnical\Model\Holiday;
use Omnical\Model\HolidayQuery;
use Omnical\Model\HolidayType;
use Omnical\Registry;
use Omnical\Testing\RecordedHttpClient;
use PHPUnit\Framework\TestCase;

/** A provider described by a definition file: loaded at run time, no class of its own, held to its policy. */
final class DeclarativeTest extends TestCase
{
    private const DEFINITION = __DIR__.'/Fixtures/declarative/provider.json';
    private const UA = 'omnical-tests/1.0 (+https://github.com/glitchr-omnisense/omnical; tests@example.org)';

    public function testTemplatesPointersAndFields(): void
    {
        $from = new \DateTimeImmutable('2027-01-01');
        self::assertSame('2027-01-01', Template::render('{from}', ['from' => $from]));
        self::assertSame('20270101', Template::render('{from:Ymd}', ['from' => $from]));
        self::assertSame('FR', Template::render('{c|upper}', ['c' => 'fr']));
        self::assertNull(Template::render('{language}', []), 'a placeholder alone, no value: left out');
        self::assertSame('/a%2Fb/2027', Template::render('/{id}/{year}', ['id' => 'a/b', 'year' => 2027], true));
        self::assertSame(['id', 'year'], Template::variables('/{id}/{year:Y}'));

        $doc = ['names' => [['lang' => 'EN', 'text' => 'Christmas'], ['lang' => 'DE', 'text' => 'Weihnachten']], 'a/b' => ['~x' => 1]];
        self::assertSame('Weihnachten', Pointer::get($doc, '/names[lang={language}]/text', ['language' => 'de']));
        self::assertSame('Christmas', Pointer::get($doc, '/names[lang={language}]/text'), 'no language asked: the first');
        self::assertNull(Pointer::get($doc, '/names[lang={language}]/text', ['language' => 'fr']));
        self::assertSame(1, Pointer::get($doc, '/a~1b/~0x'));
        self::assertSame($doc, Pointer::get($doc, '/'));

        self::assertSame('Christmas', Field::fromSpec(['/names[lang={language}]/text', '/names/0/text'], 'x')->read($doc, ['language' => 'fr']));
        self::assertSame(['EN', 'DE'], Field::fromSpec(['pointer' => '/names', 'each' => '/lang'], 'x')->read($doc));
        self::assertSame(['en', 'de'], Field::fromSpec(['pointer' => '/names', 'each' => '/lang', 'transform' => 'lower'], 'x')->read($doc));
        self::assertSame([], Field::fromSpec(['pointer' => '/none', 'transform' => 'list'], 'x')->read($doc));
        self::assertSame(false, Field::fromSpec(['pointer' => '/a~1b/~0x', 'transform' => ['bool', 'not']], 'x')->read($doc));
        self::assertSame('school', Field::fromSpec(['const' => 'school'], 'x')->read($doc));
    }

    public function testADefinitionFileIsAProviderWithItsPolicyAndCapabilities(): void
    {
        $registry = self::registry(self::recorded());
        $provider = $registry->get('de');

        self::assertInstanceOf(DeclarativeProvider::class, $provider);
        self::assertSame(['example-holidays', 'Example Holidays'], [$provider->getName(), $provider->getTitle()]);
        $capabilities = $provider->capabilities();
        self::assertSame([['holidays'], ['DE', 'FR'], true, [HolidayType::Public, HolidayType::School]], [$capabilities->contracts(), $capabilities->countries, $capabilities->regions, $capabilities->holidayTypes]);
        $policy = $registry->policy('de');
        self::assertSame(['example-holidays', 'Example Holidays', '3/minute/application/holidays'], [$policy->provider, $policy->rights->attribution->text, ($policy->rateLimits ?? [])[0]->id()]);
    }

    public function testEachYearIsAskedAndEachItemReadIntoAHoliday(): void
    {
        $http = self::recorded();
        $holidays = self::registry($http)->holidays('de')->holidays(new HolidayQuery('DE', new \DateTimeImmutable('2026-12-01'), new \DateTimeImmutable('2027-01-31'), 'DE-BY', 'de'));

        self::assertSame([
            ['2026-12-25', 'Weihnachten', true, [], 'public', false],
            ['2026-12-31', 'Silvester', true, [], 'bank', true],
            ['2027-01-06', 'Heilige Drei Könige', false, ['DE-BW', 'DE-BY', 'DE-ST'], 'public', false],
        ], array_map(static fn (Holiday $h) => [$h->first->format('Y-m-d'), $h->name, $h->nationwide, $h->regions, $h->type()->value, $h->halfDay], $holidays));
        self::assertSame(['de', 'example-holidays', 'h-1'], [$holidays[0]->language, $holidays[0]->source, $holidays[0]->id]);
        self::assertCount(2, $http->requests, 'one call per year, the school endpoint not asked');
        self::assertSame('https://holidays.example/api/public/DE/2026', $http->requests[0]['url']);
        self::assertSame(['lang' => 'DE', 'region' => 'DE-BY'], $http->requests[0]['options']['query'] ?? null);
        self::assertSame('not-a-real-key', $http->header('X-Api-Key'));
        self::assertSame(self::UA, $http->header('User-Agent'));
        self::assertSame('application/json', $http->header('Accept'));
    }

    public function testTaggedEndpointsAreCalledOnlyForTheTypesAsked(): void
    {
        $http = self::recorded();
        $holidays = self::registry($http)->holidays('de')->holidays(new HolidayQuery('DE', new \DateTimeImmutable('2026-12-01'), new \DateTimeImmutable('2027-01-31'), types: [HolidayType::School]));

        self::assertSame([['Weihnachtsferien', '2026-12-23', '2027-01-09', ['DE-BY']]], array_map(static fn (Holiday $h) => [$h->name, $h->first->format('Y-m-d'), $h->last->format('Y-m-d'), $h->regions], $holidays));
        self::assertCount(1, $http->requests);
        self::assertSame(['from' => '2026-12-01', 'to' => '20270131'], $http->requests[0]['options']['query'] ?? null);
    }

    public function testStatusesReadAsEmptyErrorsAndTheGuard(): void
    {
        $http = RecordedHttpClient::routes(['~/public/XX/~' => [400, '{"title":"Unknown country"}'], '~/public/FR/~' => [500, 'key=not-a-real-key failed']]);
        $registry = self::registry($http);
        self::assertSame([], $registry->holidays('de')->holidays(HolidayQuery::year('XX', 2027)), 'a 400 read as no holiday');
        try {
            $registry->holidays('de')->holidays(HolidayQuery::year('FR', 2027));
            self::fail('A 500 was read as no holiday.');
        } catch (ProviderException $e) {
            self::assertSame('Example Holidays answered 500 for GET /api/public/FR/2027. key=*** failed', $e->getMessage());
        }
        $registry->holidays('de')->holidays(HolidayQuery::year('XX', 2027));
        $this->expectException(UsageLimitReachedException::class);
        $registry->holidays('de')->holidays(HolidayQuery::year('XX', 2027));
    }

    public function testTheUserAgentThePolicyAsksIsRequired(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Example Holidays asks every request to name the application, its version and a contact');
        (new Registry([DeclarativeFactory::fromFile(self::DEFINITION, self::recorded(), new ArraySecrets([]))], ['de' => ['factory' => 'example-holidays', 'options' => ['user_agent' => 'curl/8']]]))->get('de');
    }

    public function testADefinitionThatDoesNotHoldIsRefusedAtOnce(): void
    {
        $json = (string) file_get_contents(self::DEFINITION);
        $base = self::decode($json);
        $cases = [
            'unknown keys: baseUrl' => ['baseUrl' => 'x'],
            'keeps "holidays" and declares no endpoint for it' => ['endpoints' => []],
            'No auth type "oauth2"' => ['auth' => ['type' => 'oauth2', 'secret' => 'X']],
            'needs its usage policy' => ['policy' => null],
            'needs an http(s) baseUri' => ['baseUri' => 'ftp://x'],
        ];
        foreach ($cases as $message => $change) {
            try {
                Definition::fromArray(array_merge($base, $change), self::DEFINITION);
                self::fail('Accepted: '.$message);
            } catch (InvalidConfigException $e) {
                self::assertStringContainsString($message, $e->getMessage());
            }
        }
        $calendar = str_replace(['"capabilities": ["holidays"]', '"endpoints": {'], ['"capabilities": ["holidays", "calendar-events"]', '"endpoints": {"calendar-events": {"path": "/events", "map": {"name": "/n"}},'], $json);
        try {
            (new DeclarativeFactory(Definition::fromArray(self::decode($calendar), self::DEFINITION), self::recorded()))->create(['user_agent' => self::UA]);
            self::fail('A declared calendar provider was built.');
        } catch (InvalidConfigException $e) {
            self::assertStringContainsString('keeps "holidays" only', $e->getMessage());
        }
        $this->expectExceptionMessage('maps title, which a Holiday does not hold');
        $titled = str_replace('"types": {"const": "school"}', '"types": {"const": "school"}, "title": "/t"', $json);
        (new DeclarativeFactory(Definition::fromArray(self::decode($titled), self::DEFINITION), self::recorded()))->create(['user_agent' => self::UA]);
    }

    public function testYamlNeedsSymfonyYaml(): void
    {
        if (class_exists('Symfony\Component\Yaml\Yaml')) {
            self::markTestSkipped('symfony/yaml is installed here.');
        }
        $file = sys_get_temp_dir().'/omnical-definition.yaml';
        file_put_contents($file, "name: x\n");
        $this->expectExceptionMessage('require symfony/yaml, or write the definition as JSON');
        Definition::fromFile($file);
    }

    /** @return array<string, mixed> */
    private static function decode(string $json): array
    {
        return Definition::map(json_decode($json, true), 'test');
    }

    private static function registry(RecordedHttpClient $http): Registry
    {
        return new Registry([DeclarativeFactory::fromFile(self::DEFINITION, $http, new ArraySecrets(['EXAMPLE_HOLIDAYS_KEY' => 'not-a-real-key']))], ['de' => ['factory' => 'example-holidays', 'options' => ['user_agent' => self::UA]]]);
    }

    private static function recorded(): RecordedHttpClient
    {
        return RecordedHttpClient::routes([
            '~/public/DE/2026~' => [200, '{"items": [
                {"id": "h-1", "start": "2026-12-25", "end": "2026-12-25", "kind": "Feiertag", "scope": "national", "names": [{"lang": "EN", "text": "Christmas Day"}, {"lang": "DE", "text": "Weihnachten"}]},
                {"start": "2026-12-31T00:00:00", "kind": ["Bank"], "scope": "national", "half": "true", "names": [{"lang": "DE", "text": "Silvester"}]},
                {"start": "2026-11-30", "kind": "Feiertag", "scope": "national", "names": [{"lang": "DE", "text": "Vor dem Zeitraum"}]}
            ]}'],
            '~/public/DE/2027~' => [200, '{"items": [
                {"start": "2027-01-06", "kind": "Feiertag", "scope": "regional", "areas": [{"code": "DE-BW"}, {"code": "DE-BY"}, {"code": "DE-ST"}], "names": [{"lang": "DE", "text": "Heilige Drei Könige"}]},
                {"start": "2027-01-15", "kind": "Feiertag", "scope": "regional", "areas": [{"code": "DE-SN"}], "names": [{"lang": "DE", "text": "Nur in Sachsen"}]}
            ]}'],
            '~/school/DE~' => [200, '[{"start": "2026-12-23", "end": "2027-01-09", "name": "Weihnachtsferien", "area": "DE-BY"}]'],
        ]);
    }
}
