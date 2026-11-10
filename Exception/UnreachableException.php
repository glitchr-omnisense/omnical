<?php

namespace Omnical\Exception;

/** The provider could not be reached: a network error, a timeout. */
final class UnreachableException extends \RuntimeException implements OmnicalException
{
}
