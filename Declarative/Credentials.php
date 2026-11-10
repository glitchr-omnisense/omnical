<?php

namespace Omnical\Declarative;

use Omnical\Auth\SecretsInterface;
use Omnical\Exception\InvalidConfigException;

/**
 * How a declared provider authenticates, its secret by reference only:
 *
 *   {"type": "none"}
 *   {"type": "header", "name": "X-Api-Key", "secret": "NAGER_PRO_KEY"}
 *   {"type": "query", "name": "api_key", "secret": "CALENDARIFIC_KEY"}
 *   {"type": "bearer", "secret": "SOME_TOKEN"}
 *   {"type": "basic", "username": "me", "secret": "SOME_PASSWORD"}
 *
 * "secret" names the secret (an environment variable by default); a site
 * may name another one (the factory's "secret" option). OAuth is no part of
 * it: a provider that needs a user's consent is written in code.
 */
final readonly class Credentials
{
    public const TYPES = ['none', 'header', 'query', 'bearer', 'basic'];

    public function __construct(public string $type = 'none', public ?string $name = null, public ?string $secret = null, public ?string $username = null)
    {
        if (!\in_array($type, self::TYPES, true)) {
            throw new InvalidConfigException(\sprintf('No auth type "%s" (%s; OAuth needs a provider written in code).', $type, implode(', ', self::TYPES)));
        }
        if ('none' !== $type && null === $secret) {
            throw new InvalidConfigException(\sprintf('The "%s" auth needs the reference of its secret ("secret").', $type));
        }
        if (\in_array($type, ['header', 'query'], true) && null === $name) {
            throw new InvalidConfigException(\sprintf('The "%s" auth needs the name it is sent under ("name").', $type));
        }
    }

    public static function fromSpec(mixed $spec, string $path): self
    {
        if (null === $spec || 'none' === $spec) {
            return new self();
        }
        if (!\is_array($spec) || array_is_list($spec)) {
            throw Definition::invalid($path, 'an object or "none"', $spec);
        }
        Definition::only($spec, ['type', 'name', 'secret', 'username'], $path);

        return new self(
            Definition::string($spec['type'] ?? 'none', $path.'.type'),
            null === ($spec['name'] ?? null) ? null : Definition::string($spec['name'], $path.'.name'),
            null === ($spec['secret'] ?? null) ? null : Definition::string($spec['secret'], $path.'.secret'),
            null === ($spec['username'] ?? null) ? null : Definition::string($spec['username'], $path.'.username'),
        );
    }

    /**
     * The query parameters and headers a request carries, the secret read
     * now.
     *
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    public function apply(SecretsInterface $secrets, ?string $reference = null): array
    {
        if ('none' === $this->type) {
            return [[], []];
        }
        $secret = $secrets->secret($reference ?? (string) $this->secret);

        return match ($this->type) {
            'query' => [[(string) $this->name => $secret], []],
            'header' => [[], [(string) $this->name => $secret]],
            'bearer' => [[], ['Authorization' => 'Bearer '.$secret]],
            default => [[], ['Authorization' => 'Basic '.base64_encode($this->username.':'.$secret)]],
        };
    }
}
