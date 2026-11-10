<?php

namespace Omnical\Declarative;

use Omnical\Exception\InvalidConfigException;

/**
 * One request of a declared operation and how its answer is read:
 *
 *   {
 *     "method": "GET",                        GET by default; POST sends "body" as JSON
 *     "path": "/Holidays/{country}/{year}",   templates (Template), each value URL-encoded
 *     "query": {"languageIsoCode": "{language|upper}"},
 *     "headers": {"Accept": "application/json"},
 *     "body": {"text": "{text}"},
 *     "split": "year",                        one call per calendar year of [from, to], {year} set
 *     "tags": ["school"],                     called only when the family asks for one of them
 *     "list": "",                             the pointer to the answer's items ("" or "/": the answer)
 *     "map": {"date": "/date", ...},          each field of the family's model (Field)
 *     "errors": {"400": "empty"}              a status read as no answer ("empty"), or an error ("fail")
 *   }
 *
 * A 404 is no answer, and a 429 (or a RateLimit field with nothing left) is
 * the provider asking to wait: both always, whatever "errors" says.
 */
final readonly class Endpoint
{
    public const SPLITS = ['year'];

    /**
     * @param array<string, string> $query
     * @param array<string, string> $headers
     * @param array<string, string> $body
     * @param list<string>          $tags
     * @param array<string, Field>  $map
     * @param array<int, string>    $errors
     */
    public function __construct(
        public string $path,
        public string $method = 'GET',
        public array $query = [],
        public array $headers = [],
        public array $body = [],
        public ?string $split = null,
        public array $tags = [],
        public string $list = '',
        public array $map = [],
        public array $errors = [],
    ) {
        if (null !== $split && !\in_array($split, self::SPLITS, true)) {
            throw new InvalidConfigException(\sprintf('No split "%s" (%s).', $split, implode(', ', self::SPLITS)));
        }
        foreach ($errors as $status => $reading) {
            if (!\in_array($reading, ['empty', 'fail', 'wait'], true)) {
                throw new InvalidConfigException(\sprintf('A status is read "empty", "fail" or "wait", not "%s" (%d).', $reading, $status));
            }
        }
    }

    public static function fromSpec(mixed $spec, string $path): self
    {
        if (!\is_array($spec) || array_is_list($spec)) {
            throw Definition::invalid($path, 'an object', $spec);
        }
        Definition::only($spec, ['method', 'path', 'query', 'headers', 'body', 'split', 'tags', 'list', 'map', 'errors'], $path);
        $map = [];
        foreach (Definition::map($spec['map'] ?? null, $path.'.map') as $field => $one) {
            $map[$field] = Field::fromSpec($one, $path.'.map.'.$field);
        }
        $errors = [];
        foreach (Definition::map($spec['errors'] ?? [], $path.'.errors') as $status => $reading) {
            if (!preg_match('~^[1-5]\d\d$~', $status)) {
                throw Definition::invalid($path.'.errors', 'HTTP statuses as keys', $status);
            }
            $errors[(int) $status] = Definition::string($reading, $path.'.errors.'.$status);
        }

        return new self(
            Definition::string($spec['path'] ?? null, $path.'.path'),
            strtoupper(Definition::string($spec['method'] ?? 'GET', $path.'.method')),
            Definition::texts($spec['query'] ?? [], $path.'.query'),
            Definition::texts($spec['headers'] ?? [], $path.'.headers'),
            Definition::texts($spec['body'] ?? [], $path.'.body'),
            null === ($spec['split'] ?? null) ? null : Definition::string($spec['split'], $path.'.split'),
            Definition::strings($spec['tags'] ?? [], $path.'.tags'),
            Definition::string($spec['list'] ?? '', $path.'.list', true),
            $map,
            $errors,
        );
    }

    /**
     * Whether the family's call asks for it: it has no tags, or one of those asked.
     *
     * @param list<string> $tags
     */
    public function wanted(array $tags): bool
    {
        return [] === $this->tags || [] !== array_intersect($this->tags, $tags);
    }
}
