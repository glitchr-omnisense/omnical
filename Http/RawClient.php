<?php

namespace Omnical\Http;

use Omnisense\Contracts\Policy\Backoff;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The calls JsonClient's get() and request() cannot make: any method whose
 * answer is not JSON, or is empty, or whose headers matter - CalDAV's
 * PROPFIND and REPORT (207 Multi-Status, XML), a PUT whose ETag is read, a
 * DELETE answered 204. omnisense/contracts' JsonClient::raw() underneath,
 * with omnical's exceptions: the provider's headers on every request, the
 * Backoff observed (a 429 is a UsageLimitReachedException), an error status
 * a ProviderException naming the path only, its status as its code, a
 * network failure an UnreachableException, the credentials kept out of
 * every message and trace. The statuses in $pass are given back as they are
 * (a 404 a caller reads as "not there").
 */
final class RawClient
{
    private readonly JsonClient $client;

    /** @param array<string, string> $headers sent with every request */
    public function __construct(HttpClientInterface $http, string $provider, array $headers = [], ?Backoff $backoff = null)
    {
        $this->client = new JsonClient($http, $provider, $headers, $backoff);
    }

    /**
     * @param array<string, mixed>  $options body, json or query, as Symfony's HttpClient takes them
     * @param array<string, string> $headers
     * @param list<int>             $pass    error statuses given back instead of thrown
     */
    public function request(string $method, string $url, array $options = [], array $headers = [], array $pass = [404]): Answer
    {
        return Answer::of($this->client->raw($method, $url, $options, $headers, $pass));
    }
}
