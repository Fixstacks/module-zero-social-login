<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Model;

use Magento\Framework\App\ResourceConnection;

/**
 * zero_social_login_identity: which store accounts are connected to which
 * Google, Apple or Facebook accounts.
 */
class IdentityStore
{
    private const TABLE = 'zero_social_login_identity';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Store accounts connected to this service account (one per website when
     * accounts are shared per website).
     *
     * @return int[]
     */
    public function findCustomerIds(string $provider, string $subject): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE), ['customer_id'])
            ->where('provider = ?', $provider)
            ->where('subject = ?', $subject);

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * @return array<string, array{identity_id: int, provider: string, email: ?string, created_at: string, last_login_at: ?string}> By provider
     */
    public function forCustomer(int $customerId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                $this->resource->getTableName(self::TABLE),
                ['identity_id', 'provider', 'email', 'created_at', 'last_login_at']
            )
            ->where('customer_id = ?', $customerId)
            ->order('created_at ASC');
        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            $row['identity_id'] = (int) $row['identity_id'];
            $rows[(string) $row['provider']] = $row;
        }

        return $rows;
    }

    public function add(int $customerId, Profile $profile): void
    {
        $this->resource->getConnection()->insert($this->resource->getTableName(self::TABLE), [
            'customer_id' => $customerId,
            'provider' => $profile->provider,
            'subject' => $profile->subject,
            'email' => $profile->email !== '' ? $profile->email : null,
        ]);
    }

    public function remove(int $customerId, string $provider): bool
    {
        return $this->resource->getConnection()->delete($this->resource->getTableName(self::TABLE), [
            'customer_id = ?' => $customerId,
            'provider = ?' => $provider,
        ]) > 0;
    }

    /**
     * Records a sign-in and the email the service has now.
     */
    public function touch(int $customerId, Profile $profile): void
    {
        $data = ['last_login_at' => gmdate('Y-m-d H:i:s')];
        if ($profile->email !== '') {
            $data['email'] = $profile->email;
        }
        $this->resource->getConnection()->update($this->resource->getTableName(self::TABLE), $data, [
            'customer_id = ?' => $customerId,
            'provider = ?' => $profile->provider,
        ]);
    }
}
