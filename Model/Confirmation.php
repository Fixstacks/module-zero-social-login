<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Model;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;

/**
 * Email confirmation ("Require Emails Confirmation" in Customer
 * Configuration). A service that verified the email confirms the account;
 * otherwise Magento's confirmation email goes out as for any sign-up.
 */
class Confirmation
{
    public function __construct(
        private readonly CustomerResource $customerResource,
        private readonly CustomerRegistry $customerRegistry,
        private readonly AccountManagementInterface $accountManagement
    ) {
    }

    public function isPending(CustomerInterface $customer): bool
    {
        return (string) $customer->getConfirmation() !== '';
    }

    /**
     * Marks the account confirmed, without the "account confirmed" email.
     */
    public function confirm(int $customerId): void
    {
        // A column of customer_entity: saveAttribute() handles only EAV attributes.
        $this->customerResource->getConnection()->update(
            $this->customerResource->getEntityTable(),
            ['confirmation' => null],
            ['entity_id = ?' => $customerId]
        );
        $this->customerRegistry->remove($customerId);
    }

    public function sendLink(CustomerInterface $customer): void
    {
        $this->accountManagement->resendConfirmation($customer->getEmail(), (int) $customer->getWebsiteId());
    }
}
