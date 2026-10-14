<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Model;

use Magento\Framework\Exception\LocalizedException;

/**
 * Sign-in can't go ahead; the message tells the customer why and what to do.
 */
class LoginException extends LocalizedException
{
}
