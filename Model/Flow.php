<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Model;

/**
 * One sign-in attempt, from the button to the service and back. Kept in the
 * customer session under its `state`; the callback must bring the same state
 * back (no login CSRF), and the ID token the same nonce (no replay).
 */
class Flow
{
    public const MODE_LOGIN = 'login';
    public const MODE_LINK = 'link';

    public function __construct(
        public readonly string $provider,
        public readonly string $state,
        public readonly string $nonce,
        public readonly string $codeVerifier,
        public readonly string $mode,
        public readonly string $from,
        public readonly ?int $customerId,
        public readonly int $createdAt
    ) {
    }

    /**
     * PKCE (RFC 7636) S256 challenge of the code verifier.
     */
    public function codeChallenge(): string
    {
        return Base64Url::encode(hash('sha256', $this->codeVerifier, true));
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['provider'] ?? ''),
            (string) ($data['state'] ?? ''),
            (string) ($data['nonce'] ?? ''),
            (string) ($data['codeVerifier'] ?? ''),
            (string) ($data['mode'] ?? self::MODE_LOGIN),
            (string) ($data['from'] ?? ''),
            isset($data['customerId']) ? (int) $data['customerId'] : null,
            (int) ($data['createdAt'] ?? 0)
        );
    }
}
