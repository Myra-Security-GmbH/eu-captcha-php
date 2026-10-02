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

class EuCaptcha implements EuCaptchaInterface
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
     * If $token is not provided, it is read from the request body via resolveToken()
     * ($_POST['eu-captcha-response'], falling back to an application/json request body).
     * If $remoteAddr is not provided, the client IP is resolved via resolveClientIp().
     * If $userAgent is not provided, it falls back to $_SERVER['HTTP_USER_AGENT'].
     *
     * On API or network failure, the result reflects $failDefault for stateNetwork and
     * stateToken, and null for stateTrain, so callers can distinguish between a failed
     * validation and a failed network request.
     *
     * @param string|null $token      The captcha response token submitted by the client. Falls back to $_POST, then a JSON request body.
     * @param string      $remoteAddr The client's IP address. Falls back to resolveClientIp() if empty.
     * @param string      $userAgent  The client's User-Agent header. Falls back to $_SERVER['HTTP_USER_AGENT'] if empty.
     *
     * @return EuCaptchaResult Result containing network, token, and train validation states.
     */
    public function validate(?string $token = null, string $remoteAddr = '', string $userAgent = ''): EuCaptchaResult
    {
        $token ??= $this->resolveToken();

        if (empty($remoteAddr)) {
            $remoteAddr = $this->resolveClientIp();
        }

        if (empty($userAgent)) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }

        try {
            $response = $this->client->post($this->verifyUrl, [
                'json' => [
                    'sitekey'           => $this->sitekey,
                    'secret'            => $this->secret,
                    'client_ip'         => $remoteAddr,
                    'client_token'      => $token ?? '',
                    'client_user_agent' => $userAgent,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true);

            return new EuCaptchaResult(
                stateNetwork: true,
                stateToken:   (bool) ($body['success'] ?? false),
                stateTrain:   (bool) ($body['train'] ?? false),
            );
        } catch (GuzzleException) {
            return new EuCaptchaResult(
                stateNetwork: $this->failDefault,
                stateToken:   $this->failDefault,
                stateTrain:   null,
            );
        }
    }

    /**
     * Resolves the captcha token from the current request.
     *
     * Reads $_POST['eu-captcha-response'] first. PHP only populates $_POST for
     * application/x-www-form-urlencoded and multipart/form-data requests, so for
     * JSON request bodies (e.g. Laravel or SPA clients) the token is present but
     * $_POST is empty. In that case, and only when the request's Content-Type is
     * application/json, the raw request body is decoded as JSON and the token
     * read from there. Returns an empty string when no token can be found, so
     * the API still counts the attempt.
     *
     * Both sources are client-controlled, so only scalar values are accepted. A
     * non-scalar value (e.g. `eu-captcha-response[]` or a JSON array/object) is
     * ignored rather than cast to string, which would emit an "Array to string
     * conversion" warning that some frameworks promote to an exception.
     */
    private function resolveToken(): string
    {
        if (isset($_POST['eu-captcha-response']) && is_scalar($_POST['eu-captcha-response'])) {
            return (string) $_POST['eu-captcha-response'];
        }

        if (!$this->requestHasJsonBody()) {
            return '';
        }

        $raw = $this->readRawRequestBody();

        if ($raw !== '') {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)
                && isset($decoded['eu-captcha-response'])
                && is_scalar($decoded['eu-captcha-response'])) {
                return (string) $decoded['eu-captcha-response'];
            }
        }

        return '';
    }

    /**
     * Tells whether the current request declares a JSON body.
     *
     * The raw body is only read and decoded for application/json requests, so a
     * form post that lacks the token never reaches the JSON path and a large
     * non-JSON body is never decoded.
     */
    private function requestHasJsonBody(): bool
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

        return is_string($contentType) && stripos($contentType, 'application/json') !== false;
    }

    /**
     * Reads the raw HTTP request body.
     *
     * Extracted into its own method so it can be overridden in tests. For JSON
     * request bodies php://input remains readable even after the framework has
     * parsed it, which is the case this fallback targets.
     */
    protected function readRawRequestBody(): string
    {
        $raw = file_get_contents('php://input');

        return $raw === false ? '' : $raw;
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
