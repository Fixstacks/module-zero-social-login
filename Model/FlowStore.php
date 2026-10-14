<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Model;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Math\Random;

/**
 * Sign-in attempts in progress, in the customer session. Several can be
 * open at once (two tabs); each is used once and expires after 15 minutes.
 */
class FlowStore
{
    private const SESSION_KEY = 'zero_social_login_flows';
    private const LIFETIME = 900;
    private const MAX_OPEN = 5;

    public function __construct(
        private readonly CustomerSession $session,
        private readonly Random $random
    ) {
    }

    public function start(string $provider, string $mode, string $from, ?int $customerId): Flow
    {
        $flow = new Flow(
            $provider,
            $this->random->getRandomString(43, Random::CHARS_LOWERS . Random::CHARS_UPPERS . Random::CHARS_DIGITS),
            $this->random->getRandomString(43, Random::CHARS_LOWERS . Random::CHARS_UPPERS . Random::CHARS_DIGITS),
            // RFC 7636: 43-128 characters from the unreserved set.
            $this->random->getRandomString(64, Random::CHARS_LOWERS . Random::CHARS_UPPERS . Random::CHARS_DIGITS),
            $mode,
            $from,
            $customerId,
            time()
        );
        $flows = $this->openFlows();
        $flows[$flow->state] = $flow->toArray();
        $this->session->setData(self::SESSION_KEY, array_slice($flows, -self::MAX_OPEN, null, true));

        return $flow;
    }

    /**
     * The attempt with this state, removed so it can't be used again.
     */
    public function take(string $state): ?Flow
    {
        $flows = $this->openFlows();
        $found = null;
        foreach ($flows as $key => $data) {
            if ($state !== '' && hash_equals((string) $key, $state)) {
                $found = Flow::fromArray($data);
                unset($flows[$key]);
            }
        }
        $this->session->setData(self::SESSION_KEY, $flows);

        return $found;
    }

    /**
     * @return array<string, array>
     */
    private function openFlows(): array
    {
        $flows = $this->session->getData(self::SESSION_KEY);

        return array_filter(
            is_array($flows) ? $flows : [],
            static fn ($data) => is_array($data) && (int) ($data['createdAt'] ?? 0) > time() - self::LIFETIME
        );
    }
}
