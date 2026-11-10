<?php

namespace Omnical\Declarative;

use Composer\InstalledVersions;
use Omnical\Auth\EnvSecrets;
use Omnical\Auth\SecretsInterface;
use Omnical\Config;
use Omnical\Exception\InvalidConfigException;
use Omnical\Http\JsonClient;
use Omnical\ProviderFactory;
use Omnical\ProviderInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The factory of a provider described by a definition (Definition): its
 * name is the definition's. Options: user_agent (required when the
 * provider's policy asks for one, checked against its pattern; sent on
 * every request otherwise too when given), base_uri (the definition's by
 * default: a self-hosted instance), timeout (seconds), secret (another
 * reference for the definition's credentials), policy, usage.
 *
 *   $factory = DeclarativeFactory::fromFile('vendor/omnical/openholidays/provider.json', $http);
 *   $factories = DeclarativeFactory::installed($http); // every installed package that declares one
 *
 * A package declares its definition in composer.json:
 * "extra": {"omnical": {"provider": "provider.json"}, "omnisense": {"policy": "policy.json"}}.
 */
final class DeclarativeFactory extends ProviderFactory
{
    private readonly Definition $definition;

    public function __construct(Definition|string $definition, private readonly ?HttpClientInterface $http = null, private readonly ?SecretsInterface $secrets = null)
    {
        $this->definition = $definition instanceof Definition ? $definition : Definition::fromFile($definition);
    }

    public static function fromFile(string $path, ?HttpClientInterface $http = null, ?SecretsInterface $secrets = null): self
    {
        return new self(Definition::fromFile($path), $http, $secrets);
    }

    /**
     * A factory for each installed Composer package whose composer.json
     * names a definition (extra.omnical.provider).
     *
     * @return list<self>
     */
    public static function installed(?HttpClientInterface $http = null, ?SecretsInterface $secrets = null): array
    {
        $factories = [];
        foreach (InstalledVersions::getInstalledPackages() as $package) {
            $dir = InstalledVersions::isInstalled($package) ? InstalledVersions::getInstallPath($package) : null;
            $file = null === $dir ? null : realpath($dir.'/composer.json');
            if (false === $file || null === $file) {
                continue;
            }
            $meta = json_decode((string) file_get_contents($file), true);
            $extra = \is_array($meta) && \is_array($meta['extra'] ?? null) ? $meta['extra'] : [];
            $provider = \is_array($extra['omnical'] ?? null) ? ($extra['omnical']['provider'] ?? null) : null;
            if (\is_string($provider)) {
                $factories[] = new self(\dirname($file).'/'.$provider, $http, $secrets);
            }
        }

        return $factories;
    }

    public function definition(): Definition
    {
        return $this->definition;
    }

    protected function populate(Config $c): void
    {
        $required = $this->definition->policy->requirements->userAgent ? ['user_agent'] : [];
        $c->defaults([
            'omnical.factory_name' => $this->definition->name,
            'omnical.factory_title' => $this->definition->title,
            'omnical.required_options' => $required,
            'base_uri' => $this->definition->baseUri,
            'user_agent' => null,
            'timeout' => null,
            'secret' => null,
        ]);
    }

    protected function build(Config $c): ProviderInterface
    {
        $agent = $c->string('user_agent');
        $requirements = $this->definition->policy->requirements;
        if ($requirements->userAgent && !$requirements->acceptsUserAgent($agent)) {
            throw new InvalidConfigException(\sprintf('%s asks every request to name the application, its version and a contact: "MySite/1.0 (https://example.org; contact@example.org)".', $this->definition->title));
        }
        $base = rtrim((string) $c->string('base_uri'), '/');
        if (!preg_match('~^https?://[^/\s]+~', $base)) {
            throw new InvalidConfigException(\sprintf('The "%s" provider needs a base_uri such as %s, not "%s".', $this->definition->name, $this->definition->baseUri, $base));
        }
        $http = $this->http ?? (class_exists(HttpClient::class) ? HttpClient::create(['timeout' => 20]) : throw new InvalidConfigException(\sprintf('The "%s" provider needs an HTTP client: give one to its factory, or require symfony/http-client.', $this->definition->name)));
        if (null !== $timeout = $c->string('timeout')) {
            $http = $http->withOptions(['timeout' => (float) $timeout]);
        }
        $headers = $this->definition->headers + (null === $agent ? [] : ['User-Agent' => $agent]);
        $json = new JsonClient($http, $this->definition->title, $headers, $c->backoff());

        return new DeclarativeProvider(new DeclarativeClient($this->definition, $json, $this->secrets ?? new EnvSecrets(), $base, $c->string('secret')));
    }
}
