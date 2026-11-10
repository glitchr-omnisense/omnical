<?php

namespace Omnical\Auth;

use Omnical\Exception\InvalidArgumentException;

/**
 * An OAuth 2.0 token as a token endpoint gives it (RFC 6749 §5.1): the
 * access token, the refresh token when there is one, when the access token
 * expires (a Unix time), the scopes granted. A store keeps it (toArray(),
 * fromArray()); dumping it shows no token.
 */
final readonly class OAuthToken
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken = null,
        public ?int $expiresAt = null,
        public ?string $scope = null,
        public string $tokenType = 'Bearer',
    ) {
        if ('' === $accessToken) {
            throw new InvalidArgumentException('An OAuth token needs its access token.');
        }
    }

    /**
     * A token endpoint's answer: expires_in counted from $now; the previous
     * refresh token kept when the answer brings none (Google keeps it,
     * Microsoft sends a new one each time).
     *
     * @param array<mixed> $answer
     */
    public static function fromResponse(array $answer, int $now, ?string $previousRefreshToken = null): self
    {
        $access = \is_string($answer['access_token'] ?? null) ? $answer['access_token'] : '';
        $refresh = \is_string($answer['refresh_token'] ?? null) && '' !== $answer['refresh_token'] ? $answer['refresh_token'] : $previousRefreshToken;
        $expiresIn = is_numeric($answer['expires_in'] ?? null) ? (int) $answer['expires_in'] : null;

        return new self($access, $refresh, null === $expiresIn ? null : $now + $expiresIn, \is_string($answer['scope'] ?? null) ? $answer['scope'] : null, \is_string($answer['token_type'] ?? null) ? $answer['token_type'] : 'Bearer');
    }

    /** Whether the access token is expired, or expires within $leeway seconds. A token without expiry never is. */
    public function expired(int $now, int $leeway = 60): bool
    {
        return null !== $this->expiresAt && $this->expiresAt - $leeway <= $now;
    }

    /** @return list<string> the scopes granted, when the endpoint said */
    public function scopes(): array
    {
        return null === $this->scope ? [] : array_values(array_filter(preg_split('~\s+~', trim($this->scope)) ?: []));
    }

    /** @return array{access_token: string, refresh_token: ?string, expires_at: ?int, scope: ?string, token_type: string} */
    public function toArray(): array
    {
        return ['access_token' => $this->accessToken, 'refresh_token' => $this->refreshToken, 'expires_at' => $this->expiresAt, 'scope' => $this->scope, 'token_type' => $this->tokenType];
    }

    /** @param array<mixed> $data what toArray() gave */
    public static function fromArray(array $data): self
    {
        return new self(
            \is_string($data['access_token'] ?? null) ? $data['access_token'] : '',
            \is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : null,
            is_numeric($data['expires_at'] ?? null) ? (int) $data['expires_at'] : null,
            \is_string($data['scope'] ?? null) ? $data['scope'] : null,
            \is_string($data['token_type'] ?? null) ? $data['token_type'] : 'Bearer',
        );
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['accessToken' => '***', 'refreshToken' => null === $this->refreshToken ? null : '***', 'expiresAt' => $this->expiresAt, 'scope' => $this->scope, 'tokenType' => $this->tokenType];
    }
}
