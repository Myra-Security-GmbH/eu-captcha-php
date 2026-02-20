# EU-Captcha PHP client

Privacy-first, no-cookie, no-manual-interaction bot protection for PHP 8.0+ applications. Automatically filters bots, spam, and credential-stuffing attempts without requiring any user interaction.

## Requirements

- PHP 8.0 or later
- [Guzzle](https://docs.guzzlephp.org/) (`guzzlehttp/guzzle` ^6.0 or ^7.0)

## Installation

> **Note:** This package requires PHP 8.0 or newer. If you are running PHP 5.0–7.x, use [`myra-security-gmbh/eu-captcha-old`](https://packagist.org/packages/myra-security-gmbh/eu-captcha-old) instead, which supports PHP 5.0+ via `file_get_contents()`.

```bash
composer require myra-security-gmbh/eu-captcha
```

## Getting credentials

1. Register at [app.eu-captcha.eu](https://app.eu-captcha.eu/user-registration)
2. Create a site and copy the **sitekey** and **secret** from the dashboard

## Quick start

> **Using a SPA framework?** The script tag and `<div>` approach below is for server-rendered pages.
> If you are building with React, Vue, or Angular, use the matching npm package for the frontend widget
> and continue to use this package for server-side verification only.
> See [SPA integration guides](https://docs.eu-captcha.eu/integration/spa/) for details.

Add the widget script to any page that contains a form you want to protect:

```html
<script src="https://cdn.eu-captcha.eu/verify.js" async defer></script>
```

Place the widget inside your form:

```html
<div class="eu-captcha" data-sitekey="EUCAPTCHA_SITE_KEY"></div>
```

Verify the submitted token on your server:

```php
<?php

use Myrasec\EuCaptcha;

$captcha = new EuCaptcha(
    sitekey: EUCAPTCHA_SITE_KEY,
    secret:  EUCAPTCHA_SECRET_KEY,
);

$result = $captcha->validate();

if (!$result->success()) {
    // Reject the form submission
}
```

`validate()` reads the token automatically from `$_POST['eu-captcha-response']` and the client IP from server headers, so no extra wiring is needed in the common case.

## Configuration options

All options are passed as named constructor arguments.

| Option             | Type     | Default | Description |
|--------------------|----------|---------|-------------|
| `sitekey`          | string   | —       | **Required.** Public sitekey from the dashboard. |
| `secret`           | string   | —       | **Required.** Secret key from the dashboard. Never expose this client-side. |
| `failDefault`      | bool     | `true`  | Return value used for both network and token state when the API cannot be reached. `true` = fail open (allow on error); `false` = fail closed (deny on error). |
| `checkCdnHeaders`  | bool     | `true`  | When `true`, the client IP is resolved from CDN/proxy headers (`HTTP_CLIENT_IP`, `HTTP_X_FORWARDED_FOR`, `HTTP_X_REAL_IP`) before falling back to `REMOTE_ADDR`. Set to `false` when your server is not behind a proxy, or when you pass the IP explicitly. |
| `verifyUrl`        | string   | *(production URL)* | Override the EU Captcha verify endpoint. Useful for testing. |
| `credentialsUrl`   | string   | *(production URL)* | Override the EU Captcha verify-credentials endpoint. |
| `client`           | `?Client`| `null`  | Optional Guzzle client instance for custom configuration or testing. |

## The result object

`validate()` returns an `EuCaptchaResult` with three methods:

| Method              | Returns `true` when…                                        |
|---------------------|-------------------------------------------------------------|
| `success()`         | The API was reached **and** the token is valid.             |
| `successNetwork()`  | The API call completed without a network or transport error. |
| `successToken()`    | The API reported the submitted token as valid.              |

Checking both states separately lets you distinguish a user failing the captcha from an API outage:

```php
<?php

$result = $captcha->validate();

if (!$result->successNetwork()) {
    // Could not reach the API — consider logging or alerting
}

if (!$result->successToken()) {
    // Token was rejected — the submission is likely automated
}
```

## Explicit token and IP

Pass the token and client IP explicitly when you need full control (e.g. non-standard form field names or API endpoints):

```php
<?php

$token    = $_POST['my-captcha-field'] ?? '';
$clientIp = $_SERVER['REMOTE_ADDR'];

$result = $captcha->validate($token, $clientIp);
```

## Verifying credentials

Use `verifyCredentials()` to confirm your sitekey and secret are valid without submitting a client token. This is useful for startup or configuration checks:

```php
<?php

$captcha = new EuCaptcha(sitekey: EUCAPTCHA_SITE_KEY, secret: EUCAPTCHA_SECRET_KEY);

if (!$captcha->verifyCredentials()) {
    // Credentials are invalid or the API is unreachable — log and alert
}
```

`verifyCredentials()` returns `false` on any network or API error rather than throwing, so it is safe to call during application initialisation.

## Further reading

- [Full documentation](https://docs.eu-captcha.eu)
- [PHP module guide](https://docs.eu-captcha.eu/integration/php-module/)
- [Server-side verification reference](https://docs.eu-captcha.eu/integration/server-side-verification/)
- [SPA integration guides](https://docs.eu-captcha.eu/integration/spa/) (React / Next.js, Vue / Nuxt, Angular)

## License

BSD 2-Clause. See [LICENSE](LICENSE) for details.
