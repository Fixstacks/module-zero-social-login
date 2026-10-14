<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Model;

use Magento\Framework\Url;

/**
 * Storefront URLs of the sign-in flow. The redirect URI must match, to the
 * character, the one registered with the service, so it is always built the
 * same way: HTTPS when the store has it, no session ID, no query string.
 * The frontend URL builder is used so the admin (settings comment) gets the
 * same URL as the storefront.
 */
class Urls
{
    public const ROUTE_START = 'sociallogin/login/start';
    public const ROUTE_CALLBACK = 'sociallogin/login/callback';

    public function __construct(
        private readonly Url $url
    ) {
    }

    public function callback(string $provider, ?int $storeId = null): string
    {
        if ($storeId !== null) {
            $this->url->setScope($storeId);
        }

        return $this->url->getUrl(self::ROUTE_CALLBACK, [
            'provider' => $provider,
            '_secure' => true,
            '_nosid' => true,
        ]);
    }

    /**
     * Where a button points: starts the flow with that service.
     *
     * @param string $provider
     * @param string $from login, register, checkout or account: where the customer goes back to
     */
    public function start(string $provider, string $from): string
    {
        return $this->url->getUrl(self::ROUTE_START, [
            'provider' => $provider,
            'from' => $from,
            '_secure' => true,
            '_nosid' => true,
        ]);
    }
}
