<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Model;

/**
 * Apple's client secret: a short-lived JWT signed with the .p8 key (ES256),
 * made fresh for each token request.
 * https://developer.apple.com/documentation/accountorganizationaldatasharing/creating-a-client-secret
 */
class AppleClientSecret
{
    private const AUDIENCE = 'https://appleid.apple.com';
    private const LIFETIME = 300;

    /**
     * @throws ServiceException
     */
    public function create(string $teamId, string $keyId, string $clientId, string $privateKey, ?int $now = null): string
    {
        $now ??= time();
        $key = openssl_pkey_get_private(self::pem($privateKey));
        if ($key === false) {
            throw new ServiceException('Apple: the private key can\'t be read');
        }
        $input = Base64Url::encode((string) json_encode(['alg' => 'ES256', 'kid' => $keyId]))
            . '.' . Base64Url::encode((string) json_encode([
                'iss' => $teamId,
                'iat' => $now,
                'exp' => $now + self::LIFETIME,
                'aud' => self::AUDIENCE,
                'sub' => $clientId,
            ]));
        if (!openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new ServiceException('Apple: signing the client secret failed');
        }

        return $input . '.' . Base64Url::encode(self::derToRaw($der));
    }

    /**
     * The .p8 contents as PEM, whatever happened to the line breaks on the
     * way into the settings field.
     */
    public static function pem(string $key): string
    {
        $body = preg_replace('/-----(BEGIN|END) [A-Z ]+-----|\s+/', '', $key);

        return "-----BEGIN PRIVATE KEY-----\n" . chunk_split((string) $body, 64, "\n") . "-----END PRIVATE KEY-----\n";
    }

    /**
     * OpenSSL's ECDSA signature (DER SEQUENCE of two INTEGERs) as the 64-byte
     * r||s that JWS expects (RFC 7518 3.4).
     *
     * @throws ServiceException
     */
    public static function derToRaw(string $der): string
    {
        $sequence = self::readDer($der, 0, 0x30);
        $r = self::readDer($sequence['value'], 0, 0x02);
        $s = self::readDer($sequence['value'], $r['next'], 0x02);
        $raw = '';
        foreach ([$r['value'], $s['value']] as $integer) {
            $integer = ltrim($integer, "\0");
            if (strlen($integer) > 32) {
                throw new ServiceException('Apple: unexpected signature length');
            }
            $raw .= str_pad($integer, 32, "\0", STR_PAD_LEFT);
        }

        return $raw;
    }

    /**
     * One DER element at $offset: its contents and where the next one starts.
     *
     * @return array{value: string, next: int}
     * @throws ServiceException
     */
    private static function readDer(string $der, int $offset, int $tag): array
    {
        if (strlen($der) < $offset + 2 || ord($der[$offset]) !== $tag) {
            throw new ServiceException('Apple: unexpected signature format');
        }
        $length = ord($der[$offset + 1]);
        $offset += 2;
        if ($length & 0x80) {
            $bytes = $length & 0x7f;
            $length = (int) hexdec(bin2hex(substr($der, $offset, $bytes)));
            $offset += $bytes;
        }
        if (strlen($der) < $offset + $length) {
            throw new ServiceException('Apple: unexpected signature format');
        }

        return ['value' => substr($der, $offset, $length), 'next' => $offset + $length];
    }
}
