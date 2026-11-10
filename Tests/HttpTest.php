<?php

namespace Omnical\Tests;

use Omnical\Exception\AuthenticationException;
use Omnical\Exception\ConflictException;
use Omnical\Exception\ProviderException;
use Omnical\Exception\UsageLimitReachedException;
use Omnical\Http\JsonClient;
use Omnical\Http\RawClient;
use Omnical\Testing\RecordedHttpClient;
use Omnisense\Contracts\Policy\Backoff;
use Omnisense\Contracts\Policy\InMemoryRateLimiter;
use PHPUnit\Framework\TestCase;

final class HttpTest extends TestCase
{
    public function testRawCallsGiveStatusHeadersAndBodyAndKeepTheTokenOut(): void
    {
        $http = RecordedHttpClient::routes([
            '~^PROPFIND ~' => [207, '<d:multistatus xmlns:d="DAV:"/>', ['content-type' => ['application/xml'], 'DAV' => ['1, calendar-access']]],
            '~^PUT .*/stale\.ics~' => [412, 'Precondition Failed'],
            '~^GET .*/private~' => [401, 'Bearer realm="x" token=not-a-real-token'],
            '~^DELETE ~' => [404, ''],
        ]);
        $raw = new RawClient($http, 'Example', ['Authorization' => 'Bearer not-a-real-token']);

        $answer = $raw->request('PROPFIND', 'https://dav.example/cal/', ['body' => '<propfind/>'], ['Depth' => '1']);
        self::assertSame([207, '1, calendar-access', 'application/xml'], [$answer->status, $answer->header('dav'), $answer->header('Content-Type')]);
        self::assertSame(['Depth' => '1', 'Authorization' => 'Bearer not-a-real-token'], $http->requests[0]['options']['headers'] ?? null);
        self::assertSame(404, $raw->request('DELETE', 'https://dav.example/cal/gone.ics')->status, 'a 404 is given back');
        try {
            $raw->request('PUT', 'https://dav.example/cal/stale.ics');
            self::fail('A 412 was read as written.');
        } catch (ConflictException $e) {
            self::assertSame([412, 'Example answered 412 for PUT /cal/stale.ics. Precondition Failed'], [$e->status(), $e->getMessage()]);
        }
        try {
            $raw->request('GET', 'https://dav.example/private');
            self::fail('A 401 was read as an answer.');
        } catch (AuthenticationException $e) {
            self::assertInstanceOf(ProviderException::class, $e);
            self::assertStringNotContainsString('not-a-real-token', $e->getMessage());
        }
    }

    public function testA429HoldsTheNextCallsOfBothClients(): void
    {
        $http = RecordedHttpClient::routes(['~~' => [429, '{}', ['Retry-After' => ['120']]]]);
        $backoff = new Backoff(new InMemoryRateLimiter(), 'Example', UsageLimitReachedException::class);
        try {
            (new JsonClient($http, 'Example', [], $backoff))->get('https://api.example/events');
            self::fail('A 429 was read as events.');
        } catch (UsageLimitReachedException $e) {
            self::assertSame(120, $e->retryAfter);
        }
        $this->expectException(UsageLimitReachedException::class);
        (new RawClient($http, 'Example', [], $backoff))->request('DELETE', 'https://api.example/events/1');
    }

    public function testAnEmptyAnswerIsAnEmptyDocument(): void
    {
        $answer = (new RawClient(RecordedHttpClient::routes(['~~' => [204, '']]), 'Example'))->request('DELETE', 'https://api.example/events/1');
        self::assertSame([], $answer->json()->toArray());
    }

    public function testRawCallsAreTheKernelsAnswerFieldForField(): void
    {
        $http = RecordedHttpClient::routes(['~^REPORT ~' => [207, '<d:multistatus xmlns:d="DAV:"/>', ['Set-Cookie' => ['a=1', 'b=2']]]]);
        $kernel = (new JsonClient($http, 'Example'))->raw('REPORT', 'https://dav.example/cal/', ['body' => '<report/>']);
        $answer = (new RawClient($http, 'Example'))->request('REPORT', 'https://dav.example/cal/', ['body' => '<report/>']);

        self::assertSame([$kernel->status, $kernel->headers, $kernel->body, $kernel->url], [$answer->status, $answer->headers, $answer->body, $answer->url]);
        self::assertSame(['a=1', 'b=2'], $answer->headers['set-cookie'] ?? null, 'every value of a header');
        self::assertSame('https://dav.example/cal/', $answer->url, 'the URL that answered');
        self::assertSame('', (new \Omnical\Http\Answer(204, [], ''))->url, 'an answer made by hand, as before');
    }
}
