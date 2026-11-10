<?php

namespace Omnical\Declarative;

/**
 * Where a value sits in a decoded JSON answer: a JSON Pointer (RFC 6901:
 * "/name/0/text", "~1" for a slash, "~0" for a tilde; "" and "/" both the
 * whole answer), one segment of which may pick an object out of a list by
 * one of its fields - "/name[language={language}]/text": the first item
 * whose "language" is the query's, compared without case. A pick whose
 * value is not given ({language} unset) takes the list's first item.
 */
final class Pointer
{
    /** @param array<string, mixed> $args the templates' values */
    public static function get(mixed $document, string $pointer, array $args = []): mixed
    {
        if ('' === $pointer || '/' === $pointer) {
            return $document;
        }
        if (!str_starts_with($pointer, '/')) {
            return null;
        }
        $value = $document;
        foreach (\array_slice(explode('/', $pointer), 1) as $segment) {
            $pick = null;
            if (preg_match('~^(.*)\[([^=\]]+)=([^\]]*)\]$~', $segment, $m)) {
                [$segment, $pick] = [$m[1], [$m[2], Template::render($m[3], $args)]];
            }
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
            if ('' !== $segment || null === $pick) {
                if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                    return null;
                }
                $value = $value[$segment];
            }
            if (null !== $pick) {
                $value = self::pick($value, $pick[0], $pick[1]);
            }
        }

        return $value;
    }

    private static function pick(mixed $list, string $field, ?string $wanted): mixed
    {
        if (!\is_array($list) || !array_is_list($list)) {
            return null;
        }
        if (null === $wanted) {
            return $list[0] ?? null;
        }
        foreach ($list as $item) {
            if (\is_array($item) && \is_scalar($item[$field] ?? null) && 0 === strcasecmp((string) $item[$field], $wanted)) {
                return $item;
            }
        }

        return null;
    }
}
