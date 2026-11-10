<?php

namespace Omnical\Exception;

/** A value no record can hold: an event ending before it starts, a country that is no ISO code, a rule without FREQ. */
final class InvalidArgumentException extends \InvalidArgumentException implements OmnicalException
{
}
