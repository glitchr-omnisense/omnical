<?php

namespace Omnical\Declarative;

use Omnical\Exception\InvalidConfigException;

/**
 * A definition's templates: "{country}", "{from:Y-m-d}" (a date in a format
 * of PHP's date()), "{country|lower}" (filters: upper, lower), mixed with
 * text ("/Holidays/{country}/{year}"). A template that is one placeholder
 * alone, its value null, renders null: the query parameter is left out. A
 * placeholder inside more text needs its value.
 */
final class Template
{
    private const PLACEHOLDER = '~\{([A-Za-z_][A-Za-z0-9_]*)(?::([^}|]+))?((?:\|[a-z]+)*)\}~';

    /**
     * @param array<string, mixed> $args
     * @param bool                 $encode each value URL-encoded (a path's segments)
     */
    public static function render(string $template, array $args, bool $encode = false): ?string
    {
        if (preg_match('~^'.substr(self::PLACEHOLDER, 1, -1).'$~', $template, $m)) {
            $value = self::value($m[1], $args, $m[2], $m[3]);

            return null === $value ? null : ($encode ? rawurlencode($value) : $value);
        }

        return (string) preg_replace_callback(self::PLACEHOLDER, static function (array $m) use ($args, $template, $encode): string {
            $value = self::value($m[1], $args, $m[2], $m[3]) ?? throw new InvalidConfigException(\sprintf('The template "%s" needs "%s".', $template, $m[1]));

            return $encode ? rawurlencode($value) : $value;
        }, $template);
    }

    /** @return list<string> the variables a template names */
    public static function variables(string $template): array
    {
        preg_match_all(self::PLACEHOLDER, $template, $m);

        return array_values(array_unique($m[1]));
    }

    /** @param array<string, mixed> $args */
    private static function value(string $name, array $args, string $format, string $filters): ?string
    {
        $value = $args[$name] ?? null;
        $value = match (true) {
            null === $value => null,
            $value instanceof \DateTimeInterface => $value->format('' !== $format ? $format : 'Y-m-d'),
            \is_bool($value) => $value ? 'true' : 'false',
            \is_scalar($value) => (string) $value,
            $value instanceof \BackedEnum => (string) $value->value,
            default => throw new InvalidConfigException(\sprintf('The variable "%s" is no text a template can hold (%s).', $name, get_debug_type($value))),
        };
        foreach (array_filter(explode('|', $filters)) as $filter) {
            $value = null === $value ? null : match ($filter) {
                'upper' => strtoupper($value),
                'lower' => strtolower($value),
                default => throw new InvalidConfigException(\sprintf('No template filter "%s" (upper, lower).', $filter)),
            };
        }

        return $value;
    }
}
