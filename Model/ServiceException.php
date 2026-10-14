<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\SocialLogin\Model;

/**
 * The service didn't answer, refused the code, or sent something that
 * doesn't check out. The message is for the log; the customer sees a
 * generic one.
 */
class ServiceException extends \RuntimeException
{
}
