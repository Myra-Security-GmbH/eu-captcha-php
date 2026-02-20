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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Myrasec\Exception\EuCaptchaException;

class EuCaptcha
{
    private const VERIFY_URL = 'https://api.eu-captcha.eu/v1/verify/';
    private const CREDENTIALS_URL = 'https://api.eu-captcha.eu/v1/verify-credentials';

    private Client $client;

    /**
     * @param string      $sitekey         The public site key used to identify your site on the client side.
     * @param string      $secret          The private secret key used to authenticate server-side verification requests.
     * @param string      $verifyUrl       The EU Captcha verify endpoint. Defaults to the production URL.
     * @param string      $credentialsUrl  The EU Captcha verify-credentials endpoint. Defaults to the production URL.
     * @param bool        $failDefault     Whether to treat an API communication failure as a successful validation.
     *                                     Defaults to true to avoid blocking legitimate users when the API is unreachable.
     * @param bool        $checkCdnHeaders When true (default), the client IP is resolved from CDN/proxy headers
     *                                     (HTTP_CLIENT_IP, HTTP_X_FORWARDED_FOR, HTTP_X_REAL_IP) before falling back
     *                                     to REMOTE_ADDR. Set to false when running behind no proxy, or when you
     *                                     supply $remoteAddr explicitly on every validate() call.
     * @param Client|null $client          Optional Guzzle client instance, useful for testing or custom configuration.
     *
     * @throws EuCaptchaException If sitekey or secret are empty.
     */
    public function __construct(
        private string $sitekey,
        private string $secret,
        private string $verifyUrl = self::VERIFY_URL,
        private string $credentialsUrl = self::CREDENTIALS_URL,
        private bool $failDefault = true,
        private bool $checkCdnHeaders = true,
        ?Client $client = null,
    ) {
        if (empty($this->sitekey) || empty($this->secret)) {
            throw new EuCaptchaException('sitekey and secret are required');
        }

        $this->client = $client ?? new Client();
    }

    /**
     * Validates a captcha token against the EU Captcha API.
     *
     * If $token is not provided, the value of $_POST['eu-captcha-response'] is used.
     * If $remoteAddr is not provided, the client IP is read from HTTP_X_FORWARDED_FOR or REMOTE_ADDR.
     *
     * On API or network failure, the result reflects $failDefault for stateToken
     * and false for stateNetwork, so callers can distinguish between a failed
     * validation and a failed network request.
     *
     * @param string|null $token      The captcha response token submitted by the client. Falls back to $_POST.
     * @param string      $remoteAddr The client's IP address. Falls back to resolveClientIp() if empty.
     *
     * @return EuCaptchaResult Result containing network and token validation states.
     */
    public function validate(?string $token = null, string $remoteAddr = ''): EuCaptchaResult
    {
        $token ??= $_POST['eu-captcha-response'] ?? null;

        if (empty($remoteAddr)) {
            $remoteAddr = $this->resolveClientIp();
        }

        try {
            $response = $this->client->post($this->verifyUrl, [
                'json' => [
                    'sitekey'  => $this->sitekey,
                    'secret'   => $this->secret,
                    'remoteip' => $remoteAddr,
                    'response' => $token,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true);

            return new EuCaptchaResult(
                stateNetwork: true,
                stateToken: (bool) ($body['success'] ?? false),
            );
        } catch (GuzzleException) {
            return new EuCaptchaResult(
                stateNetwork: $this->failDefault,
                stateToken: $this->failDefault,
            );
        }
    }

    /**
     * Resolves the client IP address from the current request.
     *
     * When $checkCdnHeaders is true, the following headers are checked in order and the first
     * value that passes FILTER_VALIDATE_IP is returned:
     *   HTTP_CLIENT_IP, HTTP_X_FORWARDED_FOR (first entry), HTTP_X_REAL_IP
     * Falls back to REMOTE_ADDR in all cases.
     */
    private function resolveClientIp(): string
    {
        if ($this->checkCdnHeaders) {
            foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $header) {
                if (!empty($_SERVER[$header])) {
                    $ip = trim(explode(',', $_SERVER[$header])[0]);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Checks whether the configured sitekey and secret are valid without requiring a client token.
     *
     * Intended for startup or configuration checks. Returns false on any network or API error
     * rather than throwing, so callers can log a warning and continue initialisation.
     *
     * @return bool True if the API confirms the sitekey/secret pair is valid, false otherwise.
     */
    public function verifyCredentials(): bool
    {
        try {
            $response = $this->client->post($this->credentialsUrl, [
                'json' => [
                    'sitekey' => $this->sitekey,
                    'secret'  => $this->secret,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true);

            return (bool) ($body['valid'] ?? false);
        } catch (GuzzleException) {
            return false;
        }
    }
}
