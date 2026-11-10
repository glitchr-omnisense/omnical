<?php

namespace Omnical;

use Omnical\Exception\InvalidConfigException;

/**
 * A provider's configuration while its factory builds it: omnisense/
 * contracts' Config, with omnical's prefix ("omnical.factory_name",
 * "omnical.backoff"...) and its InvalidConfigException. Every provider takes
 * two options besides its own: "policy" (the site's tightening of its usage
 * policy) and "usage" (what the site says of itself: commercial,
 * open_source, contract).
 */
final class Config extends \Omnisense\Contracts\Provider\Config
{
    /** @var string */
    public const PREFIX = 'omnical';

    protected function invalid(string $message): \Throwable
    {
        return new InvalidConfigException($message);
    }
}
