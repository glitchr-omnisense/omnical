<?php

namespace Omnical\Http;

/** What RawClient got back: the status, the headers (names in lower case), the body. */
final readonly class Answer
{
    /** @param array<string, list<string>> $headers */
    public function __construct(public int $status, public array $headers, public string $body)
    {
    }

    /** A header's first value; null when it was not sent. */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /** The body as JSON; an empty Data for an empty body (a 204). */
    public function json(): Data
    {
        return Data::of('' === trim($this->body) ? [] : json_decode($this->body, true));
    }
}
