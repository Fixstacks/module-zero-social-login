<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\ViewModel;

use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Zero\SocialLogin\Model\IdentityStore;
use Zero\SocialLogin\Model\Provider\Pool;
use Zero\SocialLogin\Model\Urls;

/**
 * My Account > Sign-In Methods: the services that are on, and any the
 * customer connected earlier (still listed, so they can be disconnected,
 * after a service is switched off).
 */
class Account implements ArgumentInterface
{
    /** @var array<string, array>|null */
    private ?array $connected = null;

    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CustomerRegistry $customerRegistry,
        private readonly IdentityStore $identities,
        private readonly Pool $pool,
        private readonly Urls $urls,
        private readonly UrlInterface $url,
        private readonly FormKey $formKey
    ) {
    }

    /**
     * @return array<int, array{code: string, label: string, connected: bool, email: string, since: string, connect_url: string, can_disconnect: bool}>
     */
    public function getRows(): array
    {
        $rows = [];
        $connected = $this->connected();
        $codes = array_unique([...array_keys($this->pool->getAvailable()), ...array_keys($connected)]);
        foreach ($codes as $code) {
            $provider = $this->pool->get($code);
            $identity = $connected[$code] ?? null;
            $rows[] = [
                'code' => $code,
                'label' => $provider?->getLabel() ?? $code,
                'connected' => $identity !== null,
                'email' => (string) ($identity['email'] ?? ''),
                'since' => (string) ($identity['created_at'] ?? ''),
                'connect_url' => $provider?->isAvailable() ? $this->urls->start($code, 'account') : '',
                'can_disconnect' => $identity !== null && $this->canDisconnect($code),
            ];
        }

        return $rows;
    }

    /**
     * The customer can still get in without this service: a password, or
     * another connected service.
     */
    public function canDisconnect(string $code): bool
    {
        $connected = $this->connected();
        if (!isset($connected[$code])) {
            return false;
        }

        return count($connected) > 1 || $this->hasPassword();
    }

    public function hasPassword(): bool
    {
        $customerId = (int) $this->customerSession->getCustomerId();

        return $customerId > 0
            && (string) $this->customerRegistry->retrieveSecureData($customerId)->getPasswordHash() !== '';
    }

    public function getDisconnectUrl(): string
    {
        return $this->url->getUrl('sociallogin/account/disconnect');
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    private function connected(): array
    {
        return $this->connected ??= $this->identities->forCustomer((int) $this->customerSession->getCustomerId());
    }
}
