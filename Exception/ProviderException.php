<?php

namespace Omnical\Exception;

/**
 * The provider answered, and not with what was asked: an error status, a
 * body it could not read. The status is the exception's code (0 for a body
 * that could not be read). Two statuses have their own class, both still
 * ProviderExceptions: 401 (AuthenticationException: the token is refused)
 * and 412 (ConflictException: the event changed since it was read).
 */
class ProviderException extends \RuntimeException implements OmnicalException
{
    final public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function answered(string $provider, string $what, int|string $status, string $detail = ''): self
    {
        $message = rtrim(\sprintf('%s answered %s for %s. %s', $provider, (string) $status, $what, mb_substr(trim(strip_tags($detail)), 0, 300)));
        $code = \is_int($status) && 200 !== $status ? $status : 0;

        return match ($code) {
            401 => new AuthenticationException($message, 401),
            412 => new ConflictException($message, 412),
            default => new self($message, $code),
        };
    }

    /** The HTTP status the provider answered; 0 when it answered a body that could not be read. */
    public function status(): int
    {
        return $this->getCode();
    }
}
