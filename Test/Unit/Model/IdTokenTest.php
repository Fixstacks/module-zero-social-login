<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Test\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zero\SocialLogin\Model\Base64Url;
use Zero\SocialLogin\Model\Flow;
use Zero\SocialLogin\Model\IdToken;
use Zero\SocialLogin\Model\ServiceException;

class IdTokenTest extends TestCase
{
    private const NOW = 1_790_000_000;

    public static function jwt(array $claims): string
    {
        return Base64Url::encode('{"alg":"RS256"}') . '.' . Base64Url::encode((string) json_encode($claims)) . '.sig';
    }

    private function claims(array $override = []): array
    {
        return array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => 'client-1',
            'sub' => '1234567890',
            'exp' => self::NOW + 3600,
            'iat' => self::NOW,
            'nonce' => 'n-1',
            'email' => 'Ann@Example.com',
            'email_verified' => true,
        ], $override);
    }

    private function read(array $claims): array
    {
        return (new IdToken())->claims(
            self::jwt($claims),
            ['https://accounts.google.com', 'accounts.google.com'],
            'client-1',
            'n-1',
            self::NOW
        );
    }

    public function testValidTokenGivesItsClaims(): void
    {
        $this->assertSame('1234567890', $this->read($this->claims())['sub']);
        $this->assertSame('1234567890', $this->read($this->claims(['aud' => ['other', 'client-1']]))['sub']);
    }

    #[DataProvider('rejected')]
    public function testRejects(array $override, string $why): void
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage($why);
        $this->read($this->claims($override));
    }

    public static function rejected(): array
    {
        return [
            'another issuer' => [['iss' => 'https://evil.example'], 'issuer'],
            'another app' => [['aud' => 'client-2'], 'audience'],
            'expired' => [['exp' => self::NOW - 301], 'expired'],
            'from the future' => [['iat' => self::NOW + 301], 'future'],
            'replayed (other nonce)' => [['nonce' => 'n-2'], 'nonce'],
            'no nonce' => [['nonce' => null], 'nonce'],
            'no subject' => [['sub' => ''], 'subject'],
        ];
    }

    public function testRejectsGarbage(): void
    {
        $this->expectException(ServiceException::class);
        (new IdToken())->claims('not-a-jwt', ['x'], 'client-1', 'n-1');
    }

    public function testEmailVerifiedAsBooleanOrAppleString(): void
    {
        $this->assertTrue(IdToken::isTrue(true));
        $this->assertTrue(IdToken::isTrue('true'));
        $this->assertFalse(IdToken::isTrue('false'));
        $this->assertFalse(IdToken::isTrue(null));
        $this->assertFalse(IdToken::isTrue(1));
    }

    public function testPkceChallengeMatchesRfc7636Example(): void
    {
        $flow = new Flow('google', 's', 'n', 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk', Flow::MODE_LOGIN, 'login', null, 0);

        $this->assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', $flow->codeChallenge());
        $this->assertEquals($flow, Flow::fromArray($flow->toArray()));
    }
}
