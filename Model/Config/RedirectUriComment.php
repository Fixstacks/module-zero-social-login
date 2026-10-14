<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Model\Config;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Zero\SocialLogin\Model\Urls;

/**
 * The comment under a service's Enabled setting: the redirect URI to
 * register with that service, for the default store view (URLs with store
 * codes differ per store view; each one has to be registered).
 */
class RedirectUriComment implements CommentInterface
{
    public function __construct(
        private readonly Urls $urls,
        private readonly StoreManagerInterface $storeManager,
        private readonly Escaper $escaper,
        private readonly string $provider = '',
        private readonly string $console = ''
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCommentText($elementValue)
    {
        $store = $this->storeManager->getDefaultStoreView();
        $uri = $store ? $this->urls->callback($this->provider, (int) $store->getId()) : '';

        return $this->escaper->escapeHtml(__('Register this redirect URI in %1:', $this->console))
            . '<br><code style="user-select:all">' . $this->escaper->escapeHtml($uri) . '</code>';
    }
}
