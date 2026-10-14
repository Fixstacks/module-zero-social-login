<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Model\Provider;

use Zero\SocialLogin\Model\Flow;
use Zero\SocialLogin\Model\Profile;
use Zero\SocialLogin\Model\ServiceException;

/**
 * A sign-in service. Register another one in di.xml
 * (Zero\SocialLogin\Model\Provider\Pool, `providers`, keyed by its code) and
 * give it a settings group zero_social_login/<code> with `enabled`.
 */
interface ProviderInterface
{
    /**
     * Code used in URLs, settings and the identity table, e.g. `google`.
     */
    public function getCode(): string;

    /**
     * Name on the buttons ("Continue with %1").
     */
    public function getLabel(): string;

    /**
     * Switched on and its keys filled in, for the current store view.
     */
    public function isAvailable(): bool;

    /**
     * The service's sign-in page for this attempt.
     */
    public function authorizationUrl(Flow $flow, string $redirectUri): string;

    /**
     * Exchanges the authorization code from the callback for the customer's profile.
     *
     * @param Flow $flow The attempt the callback belongs to (state already checked)
     * @param string $redirectUri The same redirect URI as in the authorization request
     * @param array<string, string> $params The callback's parameters (`code`, Apple's `user`)
     * @throws ServiceException
     */
    public function profile(Flow $flow, string $redirectUri, array $params): Profile;
}
