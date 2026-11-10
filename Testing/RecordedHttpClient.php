<?php

namespace Omnical\Testing;

/**
 * An HTTP client that answers from what it was given - a callable of the
 * method, the URL and the options, returning [status, body, headers] - and
 * keeps every request made ($requests): a provider's calls checked with no
 * network. omnisense/contracts' RecordedHttpClient, under omnical's name
 * (routes() may give a route's headers too: an ETag, a 429's Retry-After).
 */
final class RecordedHttpClient extends \Omnisense\Contracts\Testing\RecordedHttpClient
{
}
