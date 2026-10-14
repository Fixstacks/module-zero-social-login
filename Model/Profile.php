<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Model;

/**
 * Who signed in, as the service reports it.
 *
 * `emailVerified` is true only when the service states it has verified the
 * address (OpenID Connect `email_verified`). Only then may sign-in attach
 * the service to an existing store account with that email.
 */
class Profile
{
    public function __construct(
        public readonly string $provider,
        public readonly string $subject,
        public readonly string $email,
        public readonly bool $emailVerified,
        public readonly string $firstName = '',
        public readonly string $lastName = ''
    ) {
    }
}
