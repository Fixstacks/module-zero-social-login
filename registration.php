<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 *
 * Zero_SocialLogin: customers sign in and sign up with Google, Apple or
 * Facebook (OpenID Connect / OAuth 2.0, no third-party libraries), on the
 * sign-in, registration and checkout pages, and connect or disconnect those
 * accounts under My Account. Admin sign-in is not affected. Off by default:
 * Stores > Configuration > Zero > Social Login.
 */
use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Zero_SocialLogin', __DIR__);
