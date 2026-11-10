<?php

namespace Omnical\Declarative;

use Omnical\Auth\SecretsInterface;
use Omnisense\Contracts\Exception\ExceptionInterface;
use Omnisense\Contracts\Http\JsonClient;

/**
 * A definition's operations called: each endpoint the family asks for (by
 * its tags), once per year when it splits by year, its path, query, headers
 * and body rendered from the call's arguments, the credentials read from
 * the secrets at that moment; each item of each answer read into the fields
 * its map names. What it gives back are plain values (strings, numbers,
 * booleans, lists): the family's provider makes its models of them.
 *
 * A 404 is no item; a status the endpoint reads "empty" is no item either;
 * any other error is the JsonClient's (the family's exceptions, the
 * credentials kept out of them), a 429 its UsageLimitReached.
 */
final class DeclarativeClient
{
    public function __construct(
        private readonly Definition $definition,
        private readonly JsonClient $json,
        private readonly SecretsInterface $secrets,
        private readonly ?string $baseUri = null,
        private readonly ?string $secret = null,
    ) {
    }

    public function definition(): Definition
    {
        return $this->definition;
    }

    /**
     * @param array<string, mixed> $args the call's values, "from" and "to" moments for a split by year
     * @param list<string>         $tags the endpoints' tags asked
     *
     * @return list<array<string, mixed>> each item, by field
     */
    public function call(string $operation, array $args, array $tags = []): array
    {
        $items = [];
        foreach ($this->definition->endpoints($operation) as $endpoint) {
            if (!$endpoint->wanted($tags)) {
                continue;
            }
            foreach (self::split($endpoint, $args) as $one) {
                array_push($items, ...$this->one($endpoint, $one));
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return list<array<string, mixed>>
     */
    private function one(Endpoint $endpoint, array $args): array
    {
        [$authQuery, $authHeaders] = $this->definition->credentials->apply($this->secrets, $this->secret);
        $query = $authQuery;
        foreach ($endpoint->query as $name => $template) {
            $value = Template::render($template, $args);
            if (null !== $value) {
                $query[$name] = $value;
            }
        }
        $headers = $authHeaders + array_filter(array_map(static fn (string $t) => Template::render($t, $args), $endpoint->headers), static fn (?string $v) => null !== $v);
        $options = [] === $query ? [] : ['query' => $query];
        if ([] !== $endpoint->body) {
            $options['json'] = array_filter(array_map(static fn (string $t) => Template::render($t, $args), $endpoint->body), static fn (?string $v) => null !== $v);
        }
        $url = rtrim($this->baseUri ?? $this->definition->baseUri, '/').(string) Template::render($endpoint->path, $args, true);
        try {
            $data = $this->json->request($endpoint->method, $url, $options, $headers);
        } catch (ExceptionInterface $e) {
            if ('empty' === ($endpoint->errors[(int) $e->getCode()] ?? null) && $e->getCode() >= 400) {
                return [];
            }
            throw $e;
        }
        $list = null === $data ? [] : Pointer::get($data->toArray(), $endpoint->list, $args);
        if (!\is_array($list)) {
            return [];
        }
        $items = [];
        foreach (array_is_list($list) ? $list : [$list] as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $fields = [];
            foreach ($endpoint->map as $field => $reader) {
                $fields[$field] = $reader->read($item, $args);
            }
            $items[] = $fields;
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return list<array<string, mixed>>
     */
    private static function split(Endpoint $endpoint, array $args): array
    {
        $from = $args['from'] ?? null;
        $to = $args['to'] ?? null;
        if ('year' !== $endpoint->split || !$from instanceof \DateTimeInterface || !$to instanceof \DateTimeInterface) {
            return [$args];
        }
        $calls = [];
        $zone = $from->getTimezone();
        for ($year = (int) $from->format('Y'); $year <= (int) $to->format('Y'); ++$year) {
            $first = new \DateTimeImmutable($year.'-01-01', $zone);
            $last = new \DateTimeImmutable($year.'-12-31', $zone);
            $calls[] = ['year' => $year, 'from' => max(\DateTimeImmutable::createFromInterface($from), $first), 'to' => min(\DateTimeImmutable::createFromInterface($to), $last)] + $args;
        }

        return $calls;
    }
}
