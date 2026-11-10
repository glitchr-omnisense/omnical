<?php

namespace Omnical\Exception;

/** A provider asked for with options it cannot work with, a definition file that does not hold, a secret not found. */
final class InvalidConfigException extends \InvalidArgumentException implements OmnicalException
{
}
