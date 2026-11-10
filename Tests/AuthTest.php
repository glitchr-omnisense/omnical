<?php

namespace Omnical\Tests;

use Omnical\Auth\ArraySecrets;
use Omnical\Auth\EnvSecrets;
use Omnical\Auth\InMemoryTokenStore;
use Omnical\Auth\OAuth2Client;
use Omnical\Auth\OAuthToken;
use Omnical\Auth\RefreshingAccessToken;
use Omnical\Auth\StaticAccessToken;
use Omnical\Exception\AuthenticationException;
use Omnical\Exception\InvalidConfigException;
use Omnical\Testing\RecordedHttpClient;
use PHPUnit\Framework\TestCase;

/** OAuth 2.0 (code, PKCE, refresh) and secrets by reference: no secret ever in a message. */
final class AuthTest extends TestCase
{
    private const SECRET = 'a-client-secret-never-shown';

    public function testTheAuthorizationAddressCarriesPkceAndTheProvidersOwnParameters(): void
    {
        [$verifier, $challenge] = OAuth2Client::pkce();
        self::assertMatchesRegularExpression('~^[A-Za-z0-9_-]{64}$~', $verifier);
        self::assertSame(rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), $challenge);

        $url = self::client(RecordedHttpClient::routes([]))->authorizationUrl('https://site.example/oauth/back', ['openid', 'https://www.googleapis.com/auth/calendar'], 'state-1', 'abc', ['access_type' => 'offline', 'prompt' => 'consent']);
        self::assertSame('https://accounts.example/authorize?response_type=code&client_id=client-1&redirect_uri=https%3A%2F%2Fsite.example%2Foauth%2Fback&scope=openid%20https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fcalendar&state=state-1&code_challenge=abc&code_challenge_method=S256&access_type=offline&prompt=consent', $url);
    }

    public function testACodeIsExchangedAndTheTokenRefreshedKeepingOrReplacingItsRefreshToken(): void
    {
        $http = RecordedHttpClient::routes(['~^POST https://accounts\.example/token~' => [200, '{"access_token":"at-1","expires_in":3599,"refresh_token":"rt-1","scope":"openid calendar","token_type":"Bearer"}']]);
        $token = self::client($http)->exchange('the-code', 'https://site.example/back', 'the-verifier');
        self::assertSame(['at-1', 'rt-1', 1003599, ['openid', 'calendar']], [$token->accessToken, $token->refreshToken, $token->expiresAt, $token->scopes()]);
        self::assertSame(['grant_type' => 'authorization_code', 'code' => 'the-code', 'redirect_uri' => 'https://site.example/back', 'code_verifier' => 'the-verifier', 'client_id' => 'client-1', 'client_secret' => self::SECRET], $http->requests[0]['options']['body'] ?? null);
        self::assertStringNotContainsString('at-1', print_r($token, true), 'a dump shows no token');

        $google = RecordedHttpClient::routes(['~/token~' => [200, '{"access_token":"at-2","expires_in":3599,"token_type":"Bearer"}']]);
        self::assertSame('rt-1', self::client($google)->refresh($token)->refreshToken, 'Google sends no new refresh token: the old one stays');
        $microsoft = RecordedHttpClient::routes(['~/token~' => [200, '{"access_token":"at-2","expires_in":3599,"refresh_token":"rt-2"}']]);
        self::assertSame('rt-2', self::client($microsoft)->refresh($token)->refreshToken, 'Microsoft rotates it');
    }

    public function testATokenIsRefreshedWhenItExpiresAndSavedAtOnce(): void
    {
        $http = RecordedHttpClient::routes(['~/token~' => [200, '{"access_token":"at-2","expires_in":3600}']]);
        $store = new InMemoryTokenStore(['u1' => new OAuthToken('at-1', 'rt-1', 1000030)]);
        $now = 1000000;
        $token = new RefreshingAccessToken(self::client($http), $store, 'u1', 60, static function () use (&$now): int {
            return $now;
        });

        self::assertSame('at-2', $token->token(), 'expires within the leeway: refreshed');
        self::assertSame(['at-2', 'rt-1'], [$store->get('u1')?->accessToken, $store->get('u1')?->refreshToken]);
        self::assertSame('at-2', $token->token());
        self::assertCount(1, $http->requests);
        self::assertSame('at-2', $token->refresh(), 'refused by the provider: refreshed again');
        self::assertCount(2, $http->requests);
    }

    public function testARevokedGrantAsksForConsentWithoutShowingAnySecret(): void
    {
        $http = RecordedHttpClient::routes(['~/token~' => [400, '{"error":"invalid_grant","error_description":"Token has been expired or revoked."}']]);
        try {
            self::client($http)->refresh(new OAuthToken('at-1', 'rt-secret-value'));
            self::fail('A revoked grant gave a token.');
        } catch (AuthenticationException $e) {
            self::assertSame('Example: its token endpoint refused the grant (invalid_grant: revoked, expired or already used). The user has to sign in and consent again.', $e->getMessage());
            self::assertSame(401, $e->status());
            self::assertStringNotContainsString(self::SECRET, (string) $e->getPrevious()?->getMessage());
        }

        $this->expectException(AuthenticationException::class);
        (new RefreshingAccessToken(self::client($http), new InMemoryTokenStore(), 'nobody'))->token();
    }

    public function testSecretsAreReadByReferenceAndNeverQuoted(): void
    {
        putenv('OMNICAL_TEST_SECRET=from-the-environment');
        self::assertSame('from-the-environment', (new EnvSecrets())->secret('env:OMNICAL_TEST_SECRET'));
        self::assertSame('from-the-environment', (new StaticAccessToken(new EnvSecrets(), 'OMNICAL_TEST_SECRET'))->token());
        putenv('OMNICAL_TEST_SECRET');
        try {
            (new EnvSecrets())->secret('OMNICAL_TEST_SECRET');
            self::fail('A missing secret was found.');
        } catch (InvalidConfigException $e) {
            self::assertStringContainsString('No secret "OMNICAL_TEST_SECRET"', $e->getMessage());
        }
        try {
            (new ArraySecrets([]))->secret('ya29.a-token-pasted-by-mistake');
            self::fail('A missing secret was found.');
        } catch (InvalidConfigException $e) {
            self::assertStringNotContainsString('ya29', $e->getMessage(), 'what looks like a value is never quoted');
        }
        self::assertStringNotContainsString('s3cr3t', print_r(new ArraySecrets(['K' => 's3cr3t']), true));

        $this->expectException(AuthenticationException::class);
        (new StaticAccessToken(new ArraySecrets(['T' => 'x']), 'T'))->refresh();
    }

    private static function client(RecordedHttpClient $http): OAuth2Client
    {
        return new OAuth2Client($http, 'Example', 'https://accounts.example/authorize', 'https://accounts.example/token', 'client-1', new ArraySecrets(['EXAMPLE_SECRET' => self::SECRET]), 'EXAMPLE_SECRET', now: static fn () => 1000000);
    }
}
