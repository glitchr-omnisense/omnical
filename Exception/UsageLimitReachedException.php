<?php

namespace Omnical\Exception;

use Omnisense\Contracts\Policy\UsageLimitReached;

/**
 * A call refused before it was made: a rate limit of the provider's usage
 * policy (as the site tightened it) is reached, or the provider asked to
 * wait (a 429, its Retry-After, a RateLimit field with nothing left).
 * $retryAfter says in how many seconds to try again, when it is known.
 */
final class UsageLimitReachedException extends UsageLimitReached implements OmnicalException
{
}
