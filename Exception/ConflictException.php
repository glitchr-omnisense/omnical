<?php

namespace Omnical\Exception;

/** The event changed since it was read (412 Precondition Failed on its ETag): read it again before writing. */
final class ConflictException extends ProviderException
{
}
