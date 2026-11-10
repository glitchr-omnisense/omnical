<?php

namespace Omnical\Declarative;

use Omnical\Exception\InvalidConfigException;
use Omnisense\Contracts\Policy\InvalidPolicyException;
use Omnisense\Contracts\Policy\UsagePolicy;

/**
 * A provider described as data, for an API that answers plain REST JSON
 * reads: its name and title, the family's contracts it keeps, its base URI,
 * how it authenticates (Credentials, by reference), the headers every
 * request carries, its operations (one or more Endpoint each), what it
 * covers ("describe": the family reads it into its Capabilities) and its
 * usage policy - the package's policy.json named ("policy": "policy.json",
 * beside the definition), or the policy itself.
 *
 * A definition is JSON (resources/declarative-provider.schema.json), or
 * YAML when symfony/yaml is installed. Unknown keys are refused, as in a
 * policy: a misspelt field must not pass for one left out.
 */
final readonly class Definition
{
    /** @var list<string> */
    public array $capabilities;

    /**
     * @param list<string>                $capabilities
     * @param array<string, string>       $headers
     * @param array<string, list<Endpoint>> $endpoints
     * @param array<string, mixed>        $describe
     */
    public function __construct(
        public string $name,
        public string $title,
        array $capabilities,
        public string $baseUri,
        public Credentials $credentials,
        public array $headers,
        public array $endpoints,
        public UsagePolicy $policy,
        public array $describe = [],
        public ?string $family = null,
        public ?string $file = null,
    ) {
        if (!preg_match('~^[a-z0-9][a-z0-9_.:-]*$~', $name)) {
            throw new InvalidConfigException(\sprintf('A declared provider\'s name is a factory name ("openholidays"), not "%s".', $name));
        }
        if (!preg_match('~^https?://[^/\s]+~', $baseUri)) {
            throw new InvalidConfigException(\sprintf('The "%s" definition needs an http(s) baseUri, not "%s".', $name, $baseUri));
        }
        $this->capabilities = array_values(array_unique($capabilities));
        foreach ($this->capabilities as $capability) {
            if ([] === ($endpoints[$capability] ?? [])) {
                throw new InvalidConfigException(\sprintf('The "%s" definition keeps "%s" and declares no endpoint for it.', $name, $capability));
            }
        }
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new InvalidConfigException(\sprintf('No provider definition at "%s".', $path));
        }
        if (preg_match('~\.ya?ml$~i', $path)) {
            $parse = ['Symfony\\Component\\Yaml\\Yaml', 'parseFile'];
            if (!class_exists($parse[0]) || !\is_callable($parse)) {
                throw new InvalidConfigException(\sprintf('"%s" is YAML: require symfony/yaml, or write the definition as JSON.', basename($path)));
            }
            $data = \call_user_func($parse, $path);
        } else {
            try {
                $data = json_decode((string) file_get_contents($path), true, 64, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new InvalidConfigException(\sprintf('The provider definition "%s" is not JSON: %s.', basename($path), $e->getMessage()), 0, $e);
            }
        }
        if (!\is_array($data) || array_is_list($data)) {
            throw new InvalidConfigException(\sprintf('The provider definition "%s" is no object.', basename($path)));
        }

        return self::fromArray(self::keyed($data), (string) realpath($path));
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ?string $file = null): self
    {
        self::only($data, ['$schema', 'name', 'title', 'family', 'capabilities', 'baseUri', 'auth', 'headers', 'endpoints', 'describe', 'policy'], '(root)');
        $name = self::string($data['name'] ?? null, 'name');
        $endpoints = [];
        foreach (self::map($data['endpoints'] ?? null, 'endpoints') as $operation => $spec) {
            $list = \is_array($spec) && array_is_list($spec) ? $spec : [$spec];
            foreach ($list as $i => $one) {
                $endpoints[$operation][] = Endpoint::fromSpec($one, 'endpoints.'.$operation.(\count($list) > 1 ? '['.$i.']' : ''));
            }
        }

        return new self(
            $name,
            self::string($data['title'] ?? $name, 'title'),
            self::strings($data['capabilities'] ?? null, 'capabilities'),
            rtrim(self::string($data['baseUri'] ?? null, 'baseUri'), '/'),
            Credentials::fromSpec($data['auth'] ?? null, 'auth'),
            self::texts($data['headers'] ?? [], 'headers'),
            $endpoints,
            self::policy($data['policy'] ?? null, $name, null === $file ? null : \dirname($file)),
            self::map($data['describe'] ?? [], 'describe'),
            null === ($data['family'] ?? null) ? null : self::string($data['family'], 'family'),
            $file,
        );
    }

    /** @return list<Endpoint> */
    public function endpoints(string $operation): array
    {
        return $this->endpoints[$operation] ?? throw new InvalidConfigException(\sprintf('The "%s" definition declares no "%s" endpoint.', $this->name, $operation));
    }

    /**
     * Every field an operation's endpoints map.
     *
     * @return list<string>
     */
    public function fields(string $operation): array
    {
        return array_values(array_unique(array_merge([], ...array_map(static fn (Endpoint $e) => array_keys($e->map), $this->endpoints[$operation] ?? []))));
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string>            $known
     */
    public static function only(array $data, array $known, string $path): void
    {
        $unknown = array_diff(array_map('strval', array_keys($data)), $known);
        if ([] !== $unknown) {
            throw new InvalidConfigException(\sprintf('The provider definition\'s %s holds unknown keys: %s (known: %s).', $path, implode(', ', $unknown), implode(', ', $known)));
        }
    }

    public static function invalid(string $path, string $expected, mixed $given): InvalidConfigException
    {
        return new InvalidConfigException(\sprintf('The provider definition\'s %s must be %s, %s given.', $path, $expected, get_debug_type($given)));
    }

    public static function string(mixed $value, string $path, bool $empty = false): string
    {
        return \is_string($value) && ($empty || '' !== trim($value)) ? $value : throw self::invalid($path, 'a string', $value);
    }

    /** @return list<string> */
    public static function strings(mixed $value, string $path): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw self::invalid($path, 'a list of strings', $value);
        }

        return array_map(static fn (mixed $one) => self::string($one, $path.'[]'), $value);
    }

    /** @return array<string, mixed> */
    public static function map(mixed $value, string $path): array
    {
        if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
            throw self::invalid($path, 'an object', $value);
        }

        return self::keyed($value);
    }

    /** @return array<string, string> */
    public static function texts(mixed $value, string $path): array
    {
        $texts = [];
        foreach (self::map($value, $path) as $key => $one) {
            $texts[$key] = \is_scalar($one) ? (string) $one : throw self::invalid($path.'.'.$key, 'a string', $one);
        }

        return $texts;
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<string, mixed>
     */
    private static function keyed(array $value): array
    {
        $keyed = [];
        foreach ($value as $key => $one) {
            $keyed[(string) $key] = $one;
        }

        return $keyed;
    }

    private static function policy(mixed $policy, string $name, ?string $dir): UsagePolicy
    {
        try {
            if (\is_string($policy)) {
                $path = str_starts_with($policy, '/') || null === $dir ? $policy : $dir.'/'.$policy;

                return UsagePolicy::fromFile($path);
            }
            if (\is_array($policy) && !array_is_list($policy)) {
                return UsagePolicy::fromArray(self::keyed($policy));
            }
        } catch (InvalidPolicyException $e) {
            throw new InvalidConfigException(\sprintf('The "%s" definition\'s policy: %s', $name, $e->getMessage()), 0, $e);
        }

        throw new InvalidConfigException(\sprintf('The "%s" definition needs its usage policy: "policy": "policy.json" (beside it) or the policy itself.', $name));
    }
}
