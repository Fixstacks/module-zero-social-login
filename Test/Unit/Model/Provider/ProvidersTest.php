<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Test\Unit\Model\Provider;

use PHPUnit\Framework\TestCase;
use Zero\SocialLogin\Model\AppleClientSecret;
use Zero\SocialLogin\Model\Config;
use Zero\SocialLogin\Model\Flow;
use Zero\SocialLogin\Model\Http;
use Zero\SocialLogin\Model\IdToken;
use Zero\SocialLogin\Model\Provider\Apple;
use Zero\SocialLogin\Model\Provider\Facebook;
use Zero\SocialLogin\Model\Provider\Google;
use Zero\SocialLogin\Model\ServiceException;
use Zero\SocialLogin\Test\Unit\Model\IdTokenTest;

class ProvidersTest extends TestCase
{
    private const REDIRECT = 'https://shop.example/sociallogin/login/callback/provider/x/';

    private function config(array $values, bool $enabled = true): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('isProviderEnabled')->willReturn($enabled);
        $config->method('getValue')->willReturnCallback(fn ($provider, $field) => $values[$field] ?? '');
        $config->method('getSecret')->willReturnCallback(fn ($provider, $field) => $values[$field] ?? '');

        return $config;
    }

    private function flow(string $provider): Flow
    {
        return new Flow($provider, 'state-1', 'nonce-1', str_repeat('v', 64), Flow::MODE_LOGIN, 'login', null, time());
    }

    private function query(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    public function testGoogleAsksForOpenIdWithPkceAndReadsTheIdToken(): void
    {
        $values = ['client_id' => 'cid.apps.googleusercontent.com', 'client_secret' => 'secret'];
        $http = $this->createMock(Http::class);
        $http->expects($this->once())->method('postForm')
            ->with('https://oauth2.googleapis.com/token', $this->callback(fn ($fields) => $fields['code'] === 'code-1'
                && $fields['code_verifier'] === str_repeat('v', 64)
                && $fields['redirect_uri'] === self::REDIRECT
                && $fields['client_secret'] === 'secret'))
            ->willReturn(['id_token' => IdTokenTest::jwt([
                'iss' => 'https://accounts.google.com', 'aud' => $values['client_id'], 'sub' => 'g-1',
                'exp' => time() + 60, 'nonce' => 'nonce-1', 'email' => ' Ann@Example.com ', 'email_verified' => true,
                'given_name' => 'Ann', 'family_name' => 'Lee',
            ])]);
        $google = new Google($this->config($values), $http, new IdToken());

        $query = $this->query($google->authorizationUrl($this->flow('google'), self::REDIRECT));
        $this->assertSame('openid email profile', $query['scope']);
        $this->assertSame('state-1', $query['state']);
        $this->assertSame('nonce-1', $query['nonce']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(self::REDIRECT, $query['redirect_uri']);

        $profile = $google->profile($this->flow('google'), self::REDIRECT, ['code' => 'code-1']);
        $this->assertSame(['google', 'g-1', 'ann@example.com', true, 'Ann', 'Lee'], [
            $profile->provider, $profile->subject, $profile->email, $profile->emailVerified,
            $profile->firstName, $profile->lastName,
        ]);
    }

    public function testGoogleUnverifiedEmailStaysUnverified(): void
    {
        $http = $this->createStub(Http::class);
        $http->method('postForm')->willReturn(['id_token' => IdTokenTest::jwt([
            'iss' => 'accounts.google.com', 'aud' => 'cid', 'sub' => 'g-1', 'exp' => time() + 60,
            'nonce' => 'nonce-1', 'email' => 'a@b.c', 'email_verified' => false,
        ])]);
        $google = new Google($this->config(['client_id' => 'cid', 'client_secret' => 's']), $http, new IdToken());

        $this->assertFalse($google->profile($this->flow('google'), self::REDIRECT, ['code' => 'c'])->emailVerified);
    }

    public function testNotAvailableWithoutKeysOrWhenOff(): void
    {
        $http = $this->createStub(Http::class);
        $this->assertFalse((new Google($this->config(['client_id' => 'cid']), $http, new IdToken()))->isAvailable());
        $this->assertFalse((new Google($this->config(['client_id' => 'c', 'client_secret' => 's'], false), $http, new IdToken()))->isAvailable());
        $this->assertTrue((new Google($this->config(['client_id' => 'c', 'client_secret' => 's']), $http, new IdToken()))->isAvailable());
        $this->assertFalse((new Facebook($this->config(['app_id' => '1']), $http))->isAvailable());
        $this->assertFalse((new Apple($this->config(['client_id' => 'c', 'team_id' => 't', 'key_id' => 'k']), $http, new IdToken(), new AppleClientSecret()))->isAvailable());
    }

    public function testAppleUsesFormPostAndTakesTheNameFromTheFirstCallback(): void
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($key, $pem);
        $values = ['client_id' => 'com.example.signin', 'team_id' => 'TEAM123456', 'key_id' => 'KEY1234567', 'private_key' => $pem];
        $http = $this->createMock(Http::class);
        $http->expects($this->once())->method('postForm')
            ->with('https://appleid.apple.com/auth/token', $this->callback(fn ($fields) => substr_count($fields['client_secret'], '.') === 2))
            ->willReturn(['id_token' => IdTokenTest::jwt([
                'iss' => 'https://appleid.apple.com', 'aud' => 'com.example.signin', 'sub' => '001234.abc',
                'exp' => time() + 60, 'nonce' => 'nonce-1', 'email' => 'x1@privaterelay.appleid.com', 'email_verified' => 'true',
            ])]);
        $apple = new Apple($this->config($values), $http, new IdToken(), new AppleClientSecret());

        $query = $this->query($apple->authorizationUrl($this->flow('apple'), self::REDIRECT));
        $this->assertSame('form_post', $query['response_mode']);
        $this->assertSame('name email', $query['scope']);

        $profile = $apple->profile($this->flow('apple'), self::REDIRECT, [
            'code' => 'c',
            'user' => '{"name":{"firstName":"Ann","lastName":"Lee"},"email":"x1@privaterelay.appleid.com"}',
        ]);
        $this->assertSame(['001234.abc', 'x1@privaterelay.appleid.com', true, 'Ann', 'Lee'], [
            $profile->subject, $profile->email, $profile->emailVerified, $profile->firstName, $profile->lastName,
        ]);
    }

    public function testFacebookProvesTheAppSecretAndNeverCountsTheEmailAsVerified(): void
    {
        $http = $this->createMock(Http::class);
        $http->expects($this->exactly(2))->method('getJson')->willReturnCallback(
            function (string $url, array $query) {
                if (str_ends_with($url, '/oauth/access_token')) {
                    $this->assertSame('code-1', $query['code']);
                    $this->assertSame(str_repeat('v', 64), $query['code_verifier']);

                    return ['access_token' => 'token-1'];
                }
                $this->assertSame(hash_hmac('sha256', 'token-1', 'app-secret'), $query['appsecret_proof']);

                return ['id' => '42', 'email' => 'ann@example.com', 'first_name' => 'Ann', 'last_name' => 'Lee'];
            }
        );
        $facebook = new Facebook($this->config(['app_id' => '1', 'app_secret' => 'app-secret']), $http);

        $this->assertSame('S256', $this->query($facebook->authorizationUrl($this->flow('facebook'), self::REDIRECT))['code_challenge_method']);
        $profile = $facebook->profile($this->flow('facebook'), self::REDIRECT, ['code' => 'code-1']);
        $this->assertSame(['42', 'ann@example.com', false], [$profile->subject, $profile->email, $profile->emailVerified]);
    }

    public function testFacebookWithoutAnAccessToken(): void
    {
        $http = $this->createStub(Http::class);
        $http->method('getJson')->willReturn(['error' => 'x']);
        $this->expectException(ServiceException::class);
        (new Facebook($this->config(['app_id' => '1', 'app_secret' => 's']), $http))
            ->profile($this->flow('facebook'), self::REDIRECT, ['code' => 'c']);
    }
}
