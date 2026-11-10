<?php

namespace Omnical\Exception;

/** A provider asked for what it does not do: the free/busy of a holiday calendar, a recurrence its API cannot say. */
final class NotSupportedException extends \LogicException implements OmnicalException
{
    public static function capability(object $provider, string $name, string $capability): self
    {
        return new self(\sprintf('The "%s" provider (%s) does not %s.', $name, $provider::class, $capability));
    }

    public static function call(string $provider, string $what): self
    {
        return new self(\sprintf('%s does not %s.', $provider, $what));
    }
}
