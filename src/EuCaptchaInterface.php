<?php

/**
 * Copyright 2026 Myra Security GmbH
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

declare(strict_types=1);

namespace Myrasec;

/**
 * Contract for the EU Captcha client.
 *
 * Type-hint against this interface in your services and controllers so that
 * Symfony (or any PSR-11 container) can autowire the concrete implementation
 * without coupling your application code to it.
 *
 * Symfony resolves the interface to the registered implementation automatically
 * when there is exactly one service that implements it. Configure the concrete
 * class in `services.yaml` and the container will inject it wherever
 * `EuCaptchaInterface` is type-hinted:
 *
 * ```yaml
 * services:
 *     Myrasec\EuCaptcha:
 *         arguments:
 *             $sitekey: '%env(EUCAPTCHA_SITE_KEY)%'
 *             $secret:  '%env(EUCAPTCHA_SECRET_KEY)%'
 *     Myrasec\EuCaptchaInterface: '@Myrasec\EuCaptcha'
 * ```
 */
interface EuCaptchaInterface
{
    /**
     * Validates a captcha token against the EU Captcha API.
     *
     * When $token is null the implementation reads $_POST['eu-captcha-response'].
     * When $remoteAddr is empty it is resolved from CDN/proxy headers or REMOTE_ADDR.
     * When $userAgent is empty it falls back to $_SERVER['HTTP_USER_AGENT'].
     *
     * @param string|null $token      The captcha response token submitted by the client.
     * @param string      $remoteAddr The client's IP address.
     * @param string      $userAgent  The client's User-Agent header.
     *
     * @return EuCaptchaResult Result containing network, token, and train validation states.
     */
    public function validate(?string $token = null, string $remoteAddr = '', string $userAgent = ''): EuCaptchaResult;

    /**
     * Checks whether the configured sitekey and secret are valid without
     * requiring a client token.
     *
     * Intended for startup or configuration checks. Returns false on any
     * network or API error rather than throwing.
     *
     * @return bool True if the API confirms the sitekey/secret pair is valid, false otherwise.
     */
    public function verifyCredentials(): bool;
}
