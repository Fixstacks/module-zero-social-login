<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Zero\SocialLogin\Model\Provider\Pool;
use Zero\SocialLogin\Model\Urls;

/**
 * The "Continue with …" buttons: one link per service that is on.
 */
class Buttons implements ArgumentInterface
{
    /** The services' logos, inline SVG (decorative: the label names the service). */
    private const ICONS = [
        'google' => '<svg class="size-5 shrink-0" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 8 3l5.7-5.7C34 6.1 29.3 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.6-.4-3.9z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 8 3l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2A11.9 11.9 0 0 1 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3a12 12 0 0 1-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.6-.4-3.9z"/></svg>',
        'apple' => '<svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.15 6.9c-.95 0-2.42-1.08-3.96-1.04-2.04.03-3.91 1.18-4.96 3.01-2.12 3.68-.55 9.1 1.52 12.09 1.01 1.45 2.2 3.09 3.79 3.04 1.52-.07 2.09-.99 3.94-.99 1.83 0 2.35.99 3.96.95 1.64-.03 2.68-1.48 3.68-2.95 1.15-1.69 1.63-3.33 1.66-3.42-.04-.01-3.18-1.22-3.22-4.86-.03-3.04 2.48-4.49 2.6-4.56-1.43-2.09-3.63-2.32-4.39-2.38-2-.16-3.68 1.09-4.62 1.09zm3.38-3.07c.84-1.01 1.4-2.43 1.25-3.83-1.21.05-2.67.8-3.54 1.82-.78.9-1.45 2.34-1.27 3.71 1.34.1 2.72-.69 3.56-1.7"/></svg>',
        'facebook' => '<svg class="size-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true"><path fill="#0866FF" d="M9.1 23.7v-8H6.63V12h2.47v-1.58c0-4.08 1.85-5.98 5.86-5.98.76 0 2.07.15 2.6.3v3.32c-.28-.03-.78-.04-1.39-.04-1.97 0-2.73.74-2.73 2.68V12h3.92l-.67 3.67h-3.25v8.25C19.4 23.24 24 18.18 24 12.04 24 5.42 18.63.04 12 .04S0 5.42 0 12.04c0 5.63 3.87 10.35 9.1 11.66z"/></svg>',
    ];

    public function __construct(
        private readonly Pool $pool,
        private readonly Urls $urls
    ) {
    }

    /**
     * @param string $from Page the customer comes back to on failure: login, register or checkout
     * @return array<int, array{code: string, label: string, url: string}>
     */
    public function getButtons(string $from): array
    {
        $buttons = [];
        foreach ($this->pool->getAvailable() as $code => $provider) {
            $buttons[] = ['code' => $code, 'label' => $provider->getLabel(), 'url' => $this->urls->start($code, $from)];
        }

        return $buttons;
    }

    public function getIcon(string $code): string
    {
        return self::ICONS[$code] ?? '';
    }
}
