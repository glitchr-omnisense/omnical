<?php

namespace Omnical\Http;

use Omnical\Exception\ProviderException;
use Omnical\Exception\UnreachableException;
use Omnisense\Contracts\Http\NoRetry;
use Omnisense\Contracts\Policy\Backoff;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A provider's JSON calls over the application's HTTP client:
 * omnisense/contracts' JsonClient with omnical's exceptions - a 404 read as
 * "not known" (null), any other error status a ProviderException naming the
 * path only, its status as its code (401 an AuthenticationException, 412 a
 * ConflictException), a network failure an UnreachableException; the
 * credentials kept out of every message. Given the provider's Backoff, a 429
 * is a UsageLimitReachedException with the wait asked, and the next calls
 * wait too.
 *
 * The client given is kept with its retries off (omnisense/contracts'
 * Http\NoRetry): Symfony's RetryableHttpClient (framework.http_client's
 * retry_failed) would send a write again on a 429, 502 or 503, and a 429
 * would never reach the Backoff. A MockHttpClient is kept as given.
 */
final class JsonClient extends \Omnisense\Contracts\Http\JsonClient
{
    /** @param array<string, string> $headers sent with every request */
    public function __construct(HttpClientInterface $http, string $provider, array $headers = [], ?Backoff $backoff = null)
    {
        // Sent once: the application's client keeps no retry of its own (omnisense/contracts' Http\NoRetry, from
        // contracts 1568f2c; a site whose vendor holds an older kernel keeps its client as given).
        parent::__construct(class_exists(NoRetry::class) ? NoRetry::of($http) : $http, $provider, $headers, $backoff);
    }

    /**
     * @param array<string, string|int|float|bool|null> $query
     * @param array<string, string>                     $headers
     *
     * @return ?Data null when the provider answers 404
     */
    public function get(string $url, array $query = [], array $headers = []): ?Data
    {
        return self::own(parent::get($url, $query, $headers));
    }

    /**
     * @param array<string, mixed>  $options json, body or query, as Symfony's HttpClient takes them
     * @param array<string, string> $headers
     *
     * @return ?Data null when the provider answers 404
     */
    public function request(string $method, string $url, array $options = [], array $headers = []): ?Data
    {
        return self::own(parent::request($method, $url, $options, $headers));
    }

    /** @param array<mixed> $data */
    protected function data(array $data): Data
    {
        return new Data($data);
    }

    protected function answered(string $what, int $status, string $detail): \Throwable
    {
        return ProviderException::answered($this->provider, $what, $status, $detail);
    }

    protected function unreachable(string $message, \Throwable $previous): \Throwable
    {
        return new UnreachableException($message, 0, $previous);
    }

    private static function own(?\Omnisense\Contracts\Http\Data $data): ?Data
    {
        return null === $data || $data instanceof Data ? $data : new Data($data->toArray());
    }
}
