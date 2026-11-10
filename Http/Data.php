<?php

namespace Omnical\Http;

/**
 * A decoded JSON answer, read by key without trusting its shape: each
 * reader gives its type or null (an empty list), whatever the provider sent.
 * A key may be a path: "start.dateTime", "emailAddress.address".
 * omnisense/contracts' Data, under omnical's name: every reader gives one of
 * these back.
 */
final readonly class Data extends \Omnisense\Contracts\Http\Data
{
}
