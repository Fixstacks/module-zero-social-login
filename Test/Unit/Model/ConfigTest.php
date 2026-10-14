<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Zero\SocialLogin\Model\Config;

/**
 * A secret is decrypted once: Magento hands it over decrypted already when
 * config.xml names the Encrypted backend model for it.
 */
#[AllowMockObjectsWithoutExpectations]
class ConfigTest extends TestCase
{
    public function testAnAlreadyDecryptedSecretIsUsedAsItIs(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->never())->method('decrypt');

        $config = $this->config('GOCSPX-secret', $encryptor);
        $this->assertSame('GOCSPX-secret', $config->getSecret('google', 'client_secret'));
    }

    public function testAnEncryptedSecretIsDecrypted(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->with('0:3:abc')->willReturn('GOCSPX-secret');

        $config = $this->config('0:3:abc', $encryptor);
        $this->assertSame('GOCSPX-secret', $config->getSecret('google', 'client_secret'));
    }

    private function config(string $stored, EncryptorInterface $encryptor): Config
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn($stored);

        return new Config($scopeConfig, $encryptor);
    }
}
