<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Zero\SocialLogin\Model\AppleClientSecret;
use Zero\SocialLogin\Model\Base64Url;
use Zero\SocialLogin\Model\ServiceException;

class AppleClientSecretTest extends TestCase
{
    public function testSignsAnEs256JwtThatVerifiesWithThePublicKey(): void
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($key, $pem);
        $public = openssl_pkey_get_details($key)['key'];
        // As pasted into the settings field: line breaks gone.
        $pasted = str_replace("\n", '', $pem);

        $jwt = (new AppleClientSecret())->create('TEAM123456', 'KEY1234567', 'com.example.signin', $pasted, 1_790_000_000);

        [$header, $payload, $signature] = explode('.', $jwt);
        $this->assertSame(['alg' => 'ES256', 'kid' => 'KEY1234567'], json_decode(Base64Url::decode($header), true));
        $this->assertSame([
            'iss' => 'TEAM123456',
            'iat' => 1_790_000_000,
            'exp' => 1_790_000_300,
            'aud' => 'https://appleid.apple.com',
            'sub' => 'com.example.signin',
        ], json_decode(Base64Url::decode($payload), true));
        $raw = Base64Url::decode($signature);
        $this->assertSame(64, strlen($raw));
        $this->assertSame(1, openssl_verify($header . '.' . $payload, $this->rawToDer($raw), $public, OPENSSL_ALGO_SHA256));
    }

    public function testUnreadableKey(): void
    {
        $this->expectException(ServiceException::class);
        (new AppleClientSecret())->create('T', 'K', 'C', 'not a key');
    }

    public function testDerToRawPadsShortIntegersAndDropsSignBytes(): void
    {
        $r = "\x00" . str_repeat("\xff", 32); // high bit set: DER adds a 0x00
        $s = str_repeat("\x01", 31);          // short: padded back to 32
        $der = "\x30" . chr(2 + 33 + 2 + 31) . "\x02\x21" . $r . "\x02\x1f" . $s;

        $this->assertSame(str_repeat("\xff", 32) . "\x00" . str_repeat("\x01", 31), AppleClientSecret::derToRaw($der));
    }

    private function rawToDer(string $raw): string
    {
        $int = static function (string $bytes): string {
            $bytes = ltrim($bytes, "\0");
            if (ord($bytes[0]) & 0x80) {
                $bytes = "\0" . $bytes;
            }

            return "\x02" . chr(strlen($bytes)) . $bytes;
        };
        $body = $int(substr($raw, 0, 32)) . $int(substr($raw, 32));

        return "\x30" . chr(strlen($body)) . $body;
    }
}
