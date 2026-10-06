# Changelog

All notable changes to `myra-security-gmbh/eu-captcha` are documented here. The project follows [Semantic Versioning](https://semver.org/).

## Unreleased

### Changed

- The JSON request-body fallback now also accepts structured JSON media types with a `+json` suffix (e.g. `application/vnd.api+json`, `application/merge-patch+json`). The media type is parsed instead of substring-matched, so `application/json` appearing only inside a parameter no longer counts.

## 2.0.1

**Upgrade from 2.0.0 immediately.** 2.0.0 cannot verify tokens: it sends `remoteip` and `response`, which the verification API rejects with `missing-input-remoteip`, so every `validate()` call fails. Install with `composer require "myra-security-gmbh/eu-captcha:^2.0.1"`, or run `composer update myra-security-gmbh/eu-captcha` to move an existing lock file off 2.0.0.

### Fixed

- `validate()` sends the fields the verification API expects: `client_ip`, `client_token` and `client_user_agent`.

### Added

- `EuCaptchaInterface`, so frameworks such as Symfony can autowire the client.
- Optional third `validate()` argument `$userAgent`; defaults to `$_SERVER['HTTP_USER_AGENT']`.
- `EuCaptchaResult::isTrain()` exposes the API's `train` flag.
- When `$_POST` has no token, `validate()` reads `eu-captcha-response` from an `application/json` request body (PHP leaves `$_POST` empty for JSON requests, e.g. Laravel or SPA clients).

### Changed

- `EuCaptchaResult::success()` returns `false` while the API reports `train: true` (training mode or disabled protection), so an unprotected sitekey fails closed.

## 2.0.0

- PHP 8.0+ client with named arguments, Guzzle transport and a result object. Superseded by 2.0.1; do not use.

## 1.0.0

- Legacy client (`EU_Captcha` class) for PHP 5.0+.
