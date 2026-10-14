<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Model;

/**
 * Reads an OpenID Connect ID token that came straight from the service's
 * token endpoint over TLS. In that case the spec lets TLS stand in for the
 * signature check (OpenID Connect Core 3.1.3.7, item 6); the claims are
 * still checked here: issuer, audience, expiry and nonce.
 */
class IdToken
{
    private const LEEWAY = 300;

    /**
     * @param string $token The `id_token` from the token response
     * @param string[] $issuers Accepted `iss` values
     * @param string $audience Our client ID
     * @param string $nonce The nonce sent with the authorization request
     * @return array The claims
     * @throws ServiceException
     */
    public function claims(string $token, array $issuers, string $audience, string $nonce, ?int $now = null): array
    {
        $now ??= time();
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new ServiceException('ID token: not a JWT');
        }
        $json = Base64Url::decode($parts[1]);
        $claims = $json === null ? null : json_decode($json, true);
        if (!is_array($claims)) {
            throw new ServiceException('ID token: unreadable claims');
        }
        if (!in_array($claims['iss'] ?? null, $issuers, true)) {
            throw new ServiceException('ID token: wrong issuer');
        }
        $aud = $claims['aud'] ?? null;
        if (!(is_array($aud) ? in_array($audience, $aud, true) : $aud === $audience)) {
            throw new ServiceException('ID token: wrong audience');
        }
        if (!is_numeric($claims['exp'] ?? null) || (int) $claims['exp'] < $now - self::LEEWAY) {
            throw new ServiceException('ID token: expired');
        }
        if (is_numeric($claims['iat'] ?? null) && (int) $claims['iat'] > $now + self::LEEWAY) {
            throw new ServiceException('ID token: issued in the future');
        }
        if (!is_string($claims['nonce'] ?? null) || !hash_equals($nonce, $claims['nonce'])) {
            throw new ServiceException('ID token: wrong nonce');
        }
        if (!is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            throw new ServiceException('ID token: no subject');
        }

        return $claims;
    }

    /**
     * `email_verified` as a boolean: Apple sends it as the string "true".
     */
    public static function isTrue(mixed $value): bool
    {
        return $value === true || $value === 'true';
    }
}
