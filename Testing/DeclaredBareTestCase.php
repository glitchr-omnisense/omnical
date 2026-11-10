<?php

namespace Omnical\Testing;

use Omnical\Declarative\DeclarativeProvider;

/**
 * The bare check of a declared provider - a package of data, no class of
 * its own: its definition is loaded and asked for holidays (answered from
 * the recorded routes given) in a PHP process of its own, and nothing may
 * load from outside omnisense/omnical, omnisense/contracts and Symfony's
 * HTTP client contracts. The core's own classes load bare too
 * (BareTestCase's checks, run on the core).
 *
 * @suggested phpunit/phpunit (test support: loaded by a package's tests only)
 */
abstract class DeclaredBareTestCase extends BareTestCase
{
    /** The definition file. */
    abstract protected static function definition(): string;

    /** @return array<string, array{0: int, 1: string}> RecordedHttpClient's routes ("@" + a file for a body) */
    abstract protected static function routes(): array;

    /** @return array{country: string, from: string, to: string, region?: string, language?: string, types?: list<string>} */
    abstract protected static function query(): array;

    /** @return array<string, mixed> the factory's options */
    protected static function options(): array
    {
        return ['user_agent' => 'omnical-bare/1.0 (+https://github.com/glitchr-omnisense/omnical; bare@example.org)'];
    }

    protected static function entryClass(): string
    {
        return DeclarativeProvider::class;
    }

    protected static function requiredPackages(): array
    {
        return ['symfony/http-client-contracts'];
    }

    /**
     * The holidays and the requests the declared provider made, loading
     * nothing but the core and what it requires.
     *
     * @return array{holidays: list<array{0: string, 1: string}>, requests: list<string>}
     */
    protected static function declared(): array
    {
        $report = self::php([self::dirOf(DeclarativeProvider::class).'/bin/declared-bare.php', static::autoload(), static::definition(), (string) json_encode(static::routes()), (string) json_encode(static::options() ?: new \stdClass()), (string) json_encode(static::query())]);
        $loaded = \is_array($report['loaded'] ?? null) ? $report['loaded'] : [];
        $strings = static fn (string $key): array => array_values(array_filter(\is_array($loaded[$key] ?? null) ? $loaded[$key] : [], 'is_string'));

        self::assertContains(DeclarativeProvider::class, $strings('symbols'));
        self::assertSame([], self::foreign($strings('symbols')), 'no class of a framework, an HTTP client or an application');
        self::assertSame([], self::outside($strings('files'), $strings('eager')), 'no file but the core and what it requires');

        $holidays = [];
        foreach (\is_array($report['holidays'] ?? null) ? $report['holidays'] : [] as $holiday) {
            if (\is_array($holiday) && \is_string($holiday[0] ?? null) && \is_string($holiday[1] ?? null)) {
                $holidays[] = [$holiday[0], $holiday[1]];
            }
        }

        return ['holidays' => $holidays, 'requests' => array_values(array_filter(\is_array($report['requests'] ?? null) ? $report['requests'] : [], 'is_string'))];
    }
}
