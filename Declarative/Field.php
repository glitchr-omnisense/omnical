<?php

namespace Omnical\Declarative;

use Omnical\Exception\InvalidConfigException;

/**
 * How one field of a model is read from an answer's item: a pointer
 * ("/startDate"), a list of pointers (the first that holds a value), or an
 * object -
 *
 *   {"pointer": "/subdivisions", "each": "/code"}            a list, each item's code
 *   {"pointer": "/type", "values": {"BackToSchool": "observance"}}
 *   {"pointer": "/nationwide", "transform": "not"}
 *   {"const": "school"}                                      the same for every item
 *   {"pointer": "/counties", "default": []}
 *
 * Transforms, in the order given: not, upper, lower, trim, string, int,
 * bool, list (a value alone made a list, null an empty one), first (a
 * list's first item), date (the YYYY-MM-DD a date or a moment starts with).
 */
final readonly class Field
{
    private const TRANSFORMS = ['not', 'upper', 'lower', 'trim', 'string', 'int', 'bool', 'list', 'first', 'date'];

    /**
     * @param list<string>          $pointers
     * @param ?array<string, mixed> $values
     * @param list<string>          $transforms
     */
    public function __construct(
        public array $pointers,
        public ?string $each = null,
        public ?array $values = null,
        public array $transforms = [],
        public mixed $default = null,
        public bool $constant = false,
        public mixed $const = null,
    ) {
        foreach ($transforms as $transform) {
            if (!\in_array($transform, self::TRANSFORMS, true)) {
                throw new InvalidConfigException(\sprintf('No transform "%s" (%s).', $transform, implode(', ', self::TRANSFORMS)));
            }
        }
    }

    public static function fromSpec(mixed $spec, string $path): self
    {
        if (\is_string($spec)) {
            return new self([$spec]);
        }
        if (\is_array($spec) && array_is_list($spec)) {
            return new self(Definition::strings($spec, $path));
        }
        if (!\is_array($spec)) {
            throw Definition::invalid($path, 'a pointer, a list of pointers or an object', $spec);
        }
        Definition::only($spec, ['pointer', 'each', 'values', 'transform', 'default', 'const'], $path);
        if (\array_key_exists('const', $spec)) {
            return new self([], constant: true, const: $spec['const']);
        }
        $pointer = $spec['pointer'] ?? throw Definition::invalid($path.'.pointer', 'a pointer', null);
        $values = $spec['values'] ?? null;
        if (null !== $values && (!\is_array($values) || array_is_list($values))) {
            throw Definition::invalid($path.'.values', 'a map', $values);
        }
        $transform = $spec['transform'] ?? [];

        return new self(
            \is_string($pointer) ? [$pointer] : Definition::strings($pointer, $path.'.pointer'),
            null === ($spec['each'] ?? null) ? null : Definition::string($spec['each'], $path.'.each'),
            null === $values ? null : array_combine(array_map('strval', array_keys($values)), array_values($values)),
            \is_string($transform) ? [$transform] : Definition::strings($transform, $path.'.transform'),
            $spec['default'] ?? null,
        );
    }

    /** @param array<string, mixed> $args */
    public function read(mixed $item, array $args = []): mixed
    {
        if ($this->constant) {
            return $this->const;
        }
        $value = null;
        foreach ($this->pointers as $pointer) {
            $value = Pointer::get($item, $pointer, $args);
            if (null !== $value) {
                break;
            }
        }
        if (null !== $this->each && \is_array($value)) {
            $value = array_values(array_filter(array_map(fn (mixed $one) => Pointer::get($one, (string) $this->each, $args), $value), static fn (mixed $v) => null !== $v));
        }
        if (null !== $this->values) {
            $value = \is_array($value) ? array_map($this->translate(...), $value) : $this->translate($value);
        }
        foreach ($this->transforms as $transform) {
            $value = self::transform($transform, $value);
        }

        return $value ?? $this->default;
    }

    private function translate(mixed $value): mixed
    {
        return \is_scalar($value) && \array_key_exists((string) $value, $this->values ?? []) ? ($this->values ?? [])[(string) $value] : $value;
    }

    private static function transform(string $transform, mixed $value): mixed
    {
        return match ($transform) {
            'not' => null === $value ? null : !$value,
            'upper' => \is_string($value) ? strtoupper($value) : (\is_array($value) ? array_map(static fn (mixed $v) => \is_string($v) ? strtoupper($v) : $v, $value) : $value),
            'lower' => \is_string($value) ? strtolower($value) : (\is_array($value) ? array_map(static fn (mixed $v) => \is_string($v) ? strtolower($v) : $v, $value) : $value),
            'trim' => \is_string($value) ? trim($value) : $value,
            'string' => \is_scalar($value) ? (string) $value : null,
            'int' => is_numeric($value) ? (int) $value : null,
            'bool' => null === $value ? null : (\is_string($value) ? \in_array(strtolower($value), ['1', 'true', 'yes'], true) : (bool) $value),
            'list' => null === $value ? [] : (\is_array($value) && array_is_list($value) ? $value : [$value]),
            'first' => \is_array($value) && array_is_list($value) ? ($value[0] ?? null) : $value,
            'date' => \is_string($value) && preg_match('~^\d{4}-\d{2}-\d{2}~', $value) ? substr($value, 0, 10) : null,
            default => $value,
        };
    }
}
