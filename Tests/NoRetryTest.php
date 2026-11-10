<?php

namespace Omnical\Tests;

use Omnical\Exception\ProviderException;
use Omnical\Http\JsonClient;
use Omnical\Http\RawClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;

/**
 * The application's http_client with retry_failed on: Symfony's
 * RetryableHttpClient would create an event again on a 503. Every
 * provider's calls go through omnical's JsonClient, which keeps the client's
 * retries off: sent once, the 503 surfaced.
 */
final class NoRetryTest extends TestCase
{
    /** @var list<string> */
    private array $sent = [];

    /** @var list<MockResponse> */
    private array $answers = [];

    public function testAnEventCreatedAnswered503IsSentOnceThroughARetryingClient(): void
    {
        $this->answers = [self::unavailable(), new MockResponse('{"id":"evt_1"}')];
        $client = new JsonClient($this->retrying(), 'Calendar');

        try {
            $client->request('POST', 'https://calendar.example/events', ['json' => ['summary' => 'Rehearsal']]);
            self::fail('a 503');
        } catch (ProviderException $e) {
            self::assertSame(503, $e->getCode());
        }
        self::assertSame(['POST https://calendar.example/events'], $this->sent);
        self::assertCount(1, $this->answers, 'the second answer was never asked for');
    }

    public function testAPutThroughTheRawClientIsSentOnce(): void
    {
        $this->answers = [self::unavailable(), new MockResponse('', ['http_code' => 201])];
        $answer = (new RawClient($this->retrying(), 'CalDAV'))->request('PUT', 'https://dav.example/cal/evt.ics', ['body' => 'BEGIN:VCALENDAR'], [], [503]);

        self::assertSame(503, $answer->status);
        self::assertCount(1, $this->sent);
    }

    public function testAMockHttpClientIsKeptAsGiven(): void
    {
        $mock = new MockHttpClient([new MockResponse('{"id":"evt_1"}')]);
        (new JsonClient($mock, 'Calendar'))->request('POST', 'https://calendar.example/events');

        self::assertSame(1, $mock->getRequestsCount());
    }

    private function retrying(): RetryableHttpClient
    {
        return new RetryableHttpClient(new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->sent[] = $method.' '.$url;

            return array_shift($this->answers) ?? new MockResponse('{}', ['http_code' => 500]);
        }), new GenericRetryStrategy(GenericRetryStrategy::DEFAULT_RETRY_STATUS_CODES, 0), 3);
    }

    private static function unavailable(): MockResponse
    {
        return new MockResponse('{"error":"unavailable"}', ['http_code' => 503]);
    }
}
