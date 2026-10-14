<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Model\Provider;

/**
 * The sign-in services, in button order (di.xml).
 */
class Pool
{
    /**
     * @param array<string, ProviderInterface> $providers By code
     */
    public function __construct(
        private readonly array $providers = []
    ) {
    }

    /**
     * The services switched on and set up for the current store view.
     *
     * @return array<string, ProviderInterface>
     */
    public function getAvailable(): array
    {
        return array_filter($this->providers, static fn (ProviderInterface $provider) => $provider->isAvailable());
    }

    public function getAvailableProvider(string $code): ?ProviderInterface
    {
        return $this->getAvailable()[$code] ?? null;
    }

    /**
     * Any registered service, set up or not (labels of connected accounts).
     */
    public function get(string $code): ?ProviderInterface
    {
        return $this->providers[$code] ?? null;
    }
}
