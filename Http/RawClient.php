<?php

namespace Omnical\Http;

use Omnical\Exception\ProviderException;
use Omnical\Exception\UnreachableException;
use Omnisense\Contracts\Policy\Backoff;
use Omnisense\Contracts\Policy\UsageLimitReached;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The calls JsonClient cannot make: any method whose answer is not JSON, or
 * is empty, or whose headers matter - CalDAV's PROPFIND and REPORT (207
 * Multi-Status, XML), a PUT whose ETag is read, a DELETE answered 204. The
 * same rules as JsonClient: the provider's headers on every request, the
 * Backoff observed (a 429 is a UsageLimitReachedException), an error status
 * a ProviderException naming the path only, its status as its code, a
 * network failure an UnreachableException, the credentials kept out of
 * every message and trace. The statuses in $pass are given back as they are
 * (a 404 a caller reads as "not there").
 */
final class RawClient
{
    /** @param array<string, string> $headers sent with every request */
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $provider,
        private readonly array $headers = [],
        private readonly ?Backoff $backoff = null,
    ) {
    }

    /**
     * @param array<string, mixed>  $options body, json or query, as Symfony's HttpClient takes them
     * @param array<string, string> $headers
     * @param list<int>             $pass    error statuses given back instead of thrown
     */
    public function request(string $method, string $url, array $options = [], array $headers = [], array $pass = [404]): Answer
    {
        $what = $method.' '.(parse_url($url, \PHP_URL_PATH) ?: '/');
        $options = ['headers' => $headers + $this->headers] + $options;
        $secrets = Redaction::secrets($url, $options);
        try {
            $this->backoff?->clear();
            $response = $this->http->request($method, $url, $options);
            $status = $response->getStatusCode();
            $received = $response->getHeaders(false);
            $this->backoff?->observe($status, $received);
            $body = $response->getContent(false);
        } catch (UsageLimitReached $e) {
            throw Redaction::withoutArguments($e);
        } catch (ExceptionInterface $e) {
            throw Redaction::withoutArguments(new UnreachableException(\sprintf('%s could not be reached for %s: %s', $this->provider, $what, Redaction::text(JsonClient::reason($e->getMessage()), $secrets)), 0, Redaction::copy($e, $secrets)));
        }
        if ($status >= 400 && !\in_array($status, $pass, true)) {
            throw Redaction::withoutArguments(ProviderException::answered($this->provider, $what, $status, Redaction::text($body, $secrets)));
        }
        $lower = [];
        foreach ($received as $name => $values) {
            $lower[strtolower($name)] = array_map('strval', $values);
        }

        return new Answer($status, $lower, $body);
    }
}
