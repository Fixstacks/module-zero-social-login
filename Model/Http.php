<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Model;

use Magento\Framework\HTTP\Client\CurlFactory;
use Psr\Log\LoggerInterface;

/**
 * Server-to-service calls (token and profile endpoints), JSON answers only.
 * Certificate checks are curl's defaults: on, which is what lets an ID token
 * received here be trusted without checking its signature (OpenID Connect
 * Core 3.1.3.7, item 6).
 */
class Http
{
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly LoggerInterface $logger,
        private readonly int $timeout = 10
    ) {
    }

    /**
     * @param array<string, string> $fields Sent as application/x-www-form-urlencoded
     * @throws ServiceException
     */
    public function postForm(string $url, array $fields): array
    {
        return $this->send('POST', $url, $fields);
    }

    /**
     * @param array<string, string> $query
     * @throws ServiceException
     */
    public function getJson(string $url, array $query = []): array
    {
        return $this->send('GET', $query ? $url . '?' . http_build_query($query) : $url, []);
    }

    private function send(string $method, string $url, array $fields): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        try {
            $curl = $this->curlFactory->create();
            $curl->setTimeout($this->timeout);
            $curl->addHeader('Accept', 'application/json');
            if ($method === 'POST') {
                $curl->post($url, $fields);
            } else {
                $curl->get($url);
            }
            $status = $curl->getStatus();
            $data = json_decode((string) $curl->getBody(), true);
        } catch (\Throwable $e) {
            $this->logger->warning('Zero Social Login: ' . $host . ': ' . $e->getMessage());
            throw new ServiceException('No answer from ' . $host, 0, $e);
        }
        if ($status !== 200 || !is_array($data)) {
            // The error code only: answers may echo the authorization code.
            $error = is_array($data) ? ($data['error']['message'] ?? $data['error'] ?? '') : '';
            $error = is_string($error) ? $error : '';
            $this->logger->warning(sprintf('Zero Social Login: %s answered HTTP %d %s', $host, $status, $error));
            throw new ServiceException(sprintf('%s answered HTTP %d', $host, $status));
        }

        return $data;
    }
}
