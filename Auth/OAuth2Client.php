<?php

namespace Omnical\Auth;

use Omnical\Exception\AuthenticationException;
use Omnical\Exception\InvalidArgumentException;
use Omnical\Exception\ProviderException;
use Omnical\Http\JsonClient;
use Omnisense\Contracts\Policy\Backoff;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The OAuth 2.0 authorization code flow (RFC 6749 §4.1) with PKCE (RFC
 * 7636), for a provider whose calendars a user opens to the site: the
 * address the user is sent to (authorizationUrl()), the code exchanged for a
 * token (exchange()), the token refreshed (refresh()). The client secret is
 * read by reference at the moment of each call; no token and no secret is
 * ever in a message. A refused refresh (invalid_grant) is an
 * AuthenticationException: the user has to consent again.
 *
 *   $oauth = new OAuth2Client($http, 'Google', 'https://accounts.google.com/o/oauth2/v2/auth',
 *       'https://oauth2.googleapis.com/token', $clientId, new EnvSecrets(), 'GOOGLE_CLIENT_SECRET');
 *   [$verifier, $challenge] = OAuth2Client::pkce();
 *   header('Location: '.$oauth->authorizationUrl($redirect, ['https://www.googleapis.com/auth/calendar'], $state, $challenge, ['access_type' => 'offline']));
 *   // back on $redirect:
 *   $store->save('user:42:google', $oauth->exchange($_GET['code'], $redirect, $verifier));
 */
final class OAuth2Client
{
    private readonly JsonClient $json;

    /** @var \Closure(): int */
    private readonly \Closure $now;

    /** @param ?\Closure(): int $now the clock (time() by default) */
    public function __construct(
        HttpClientInterface $http,
        private readonly string $provider,
        private readonly string $authorizeUrl,
        private readonly string $tokenUrl,
        private readonly string $clientId,
        private readonly SecretsInterface $secrets,
        private readonly ?string $clientSecret = null,
        ?Backoff $backoff = null,
        ?\Closure $now = null,
    ) {
        if ('' === $clientId) {
            throw new InvalidArgumentException(\sprintf('%s\'s OAuth client needs its client id.', $provider));
        }
        $this->json = new JsonClient($http, $provider, ['Accept' => 'application/json'], $backoff);
        $this->now = $now ?? time(...);
    }

    public function provider(): string
    {
        return $this->provider;
    }

    /**
     * A PKCE pair: the verifier kept by the site until the user comes back,
     * the S256 challenge sent with the user.
     *
     * @return array{0: string, 1: string}
     */
    public static function pkce(): array
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');

        return [$verifier, rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
    }

    /**
     * Where to send the user to consent.
     *
     * @param list<string>          $scopes
     * @param array<string, string> $extra  the provider's own parameters (access_type=offline, prompt=consent)
     */
    public function authorizationUrl(string $redirectUri, array $scopes, string $state, ?string $codeChallenge = null, array $extra = []): string
    {
        $query = ['response_type' => 'code', 'client_id' => $this->clientId, 'redirect_uri' => $redirectUri, 'scope' => implode(' ', $scopes), 'state' => $state];
        if (null !== $codeChallenge) {
            $query += ['code_challenge' => $codeChallenge, 'code_challenge_method' => 'S256'];
        }

        return $this->authorizeUrl.(str_contains($this->authorizeUrl, '?') ? '&' : '?').http_build_query($query + $extra, '', '&', \PHP_QUERY_RFC3986);
    }

    /** The code the user came back with, exchanged for a token. */
    public function exchange(string $code, string $redirectUri, ?string $codeVerifier = null): OAuthToken
    {
        return $this->token(array_filter(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirectUri, 'code_verifier' => $codeVerifier], static fn (?string $v) => null !== $v), null);
    }

    /** A new access token for the refresh token the given one holds. */
    public function refresh(OAuthToken $token): OAuthToken
    {
        if (null === $token->refreshToken) {
            throw AuthenticationException::consentNeeded($this->provider, 'the token has no refresh token (ask for offline access).');
        }

        return $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $token->refreshToken], $token->refreshToken);
    }

    /** @param array<string, string> $fields */
    private function token(array $fields, ?string $previousRefresh): OAuthToken
    {
        $fields['client_id'] = $this->clientId;
        if (null !== $this->clientSecret) {
            $fields['client_secret'] = $this->secrets->secret($this->clientSecret);
        }
        try {
            $answer = $this->json->request('POST', $this->tokenUrl, ['body' => $fields]);
        } catch (ProviderException $e) {
            if (\in_array($e->status(), [400, 401], true)) {
                throw AuthenticationException::consentNeeded($this->provider, 'its token endpoint refused the grant'.(str_contains($e->getMessage(), 'invalid_grant') ? ' (invalid_grant: revoked, expired or already used).' : '.'));
            }
            throw $e;
        }
        $data = $answer?->toArray() ?? [];
        if (!\is_string($data['access_token'] ?? null) || '' === $data['access_token']) {
            throw ProviderException::answered($this->provider, 'POST '.(parse_url($this->tokenUrl, \PHP_URL_PATH) ?: '/'), 200, 'No access_token in the answer.');
        }

        return OAuthToken::fromResponse($data, ($this->now)(), $previousRefresh);
    }
}
