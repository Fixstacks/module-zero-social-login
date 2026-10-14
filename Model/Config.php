<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Stores > Configuration > Zero > Social Login, for the current store view.
 */
class Config
{
    private const PREFIX = 'zero_social_login/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'general/enabled', ScopeInterface::SCOPE_STORE);
    }

    /**
     * The service is switched on (and the module is); whether its keys are
     * filled in is the provider's own check.
     */
    public function isProviderEnabled(string $provider): bool
    {
        return $this->isEnabled()
            && $this->scopeConfig->isSetFlag(self::PREFIX . $provider . '/enabled', ScopeInterface::SCOPE_STORE);
    }

    public function getValue(string $provider, string $field): string
    {
        return trim((string) $this->scopeConfig->getValue(
            self::PREFIX . $provider . '/' . $field,
            ScopeInterface::SCOPE_STORE
        ));
    }

    public function getSecret(string $provider, string $field): string
    {
        $value = $this->getValue($provider, $field);

        // Read through the Encrypted backend model its config.xml default names, the
        // value comes decrypted; one stored some other way may still be encrypted.
        return preg_match('/^\d+:\d+:/', $value) ? trim((string) $this->encryptor->decrypt($value)) : $value;
    }
}
