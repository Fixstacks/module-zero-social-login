# Zero_SocialLogin

Customers sign in and sign up with Google, Apple or
Facebook (OpenID Connect / OAuth 2.0, no third-party libraries), on the
sign-in, registration and checkout pages, and connect or disconnect those
accounts under My Account. Admin sign-in is not affected. Off by default:
Stores > Configuration > Zero > Social Login.

More in the theme's `docs/social-login.md` (https://fixstacks.com/zero/docs).

## Install

Requires Magento Open Source 2.4.7–2.4.9 and PHP 8.2–8.4.

```bash
composer require fixstacks/module-zero-social-login
bin/magento setup:upgrade
bin/magento setup:di:compile           # production mode
bin/magento setup:static-content:deploy # production mode
bin/magento cache:flush
```

Composer installs `fixstacks/module-zero-core`, `fixstacks/module-zero-checkout` with it.

Part of [`fixstacks/zero`](https://fixstacks.com/zero), which installs the Zero theme and every Zero module at once.

## License

OSL-3.0 or AFL-3.0, your choice: see `NOTICE.md`.

---

Zero is made by [Fixstacks](https://fixstacks.com/zero) in partnership with [Vilmate](https://vilmate.com).
Documentation: https://fixstacks.com/zero/docs. Demo store: https://zero.fixstacks.com.
