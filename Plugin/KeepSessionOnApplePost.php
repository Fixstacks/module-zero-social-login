<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Plugin;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Session\SessionStartChecker;
use Zero\SocialLogin\Model\Urls;

/**
 * No session for Apple's cross-site POST to the redirect URI. The browser
 * doesn't send the session cookie with it (SameSite=Lax); a session started
 * here would be a new, empty one, and its cookie would replace the
 * customer's (signing them out of the attempt and emptying a guest's cart).
 * Controller\Login\Callback only redirects that POST to a GET. Same approach
 * as Magento_Paypal's TransparentSessionChecker.
 */
class KeepSessionOnApplePost
{
    public function __construct(
        private readonly Http $request
    ) {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCheck(SessionStartChecker $subject, bool $result): bool
    {
        if (!$result || !$this->request->isPost()) {
            return $result;
        }

        return !str_contains((string) $this->request->getPathInfo(), '/' . Urls::ROUTE_CALLBACK . '/');
    }
}
