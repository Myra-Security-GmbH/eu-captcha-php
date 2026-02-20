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

class EuCaptchaResult
{
    /**
     * @param bool      $stateNetwork Whether the API request completed successfully.
     * @param bool      $stateToken   Whether the captcha token was accepted as valid by the API.
     * @param bool|null $stateTrain   The `train` flag from the API response. True means the API
     *                                skipped real validation and forced success (misconfigured
     *                                credentials or disabled protection). False means normal
     *                                operation. Null when no API response was received (network
     *                                failure).
     */
    public function __construct(
        private bool $stateNetwork,
        private bool $stateToken,
        private ?bool $stateTrain = null,
    ) {}

    /**
     * Returns true only if the API request succeeded, the token was valid,
     * and the `train` flag is not set.
     *
     * When `train` is true the API forced `success` to true without performing
     * real validation (misconfigured credentials or disabled protection). This
     * method treats that case as a failure so misconfigured sites fail securely
     * by default. Use `isTrain()` to inspect the flag directly.
     */
    public function success(): bool
    {
        return $this->stateNetwork && $this->stateToken && !$this->stateTrain;
    }

    /**
     * Returns true if the API request completed without a network or HTTP error.
     */
    public function successNetwork(): bool
    {
        return $this->stateNetwork;
    }

    /**
     * Returns true if the API confirmed the captcha token as valid.
     *
     * Note: this reflects the raw `success` field from the API response. When
     * `isTrain()` returns true, the API forced this field to true without real
     * validation. Prefer `success()` for the safe combined check.
     */
    public function successToken(): bool
    {
        return $this->stateToken;
    }

    /**
     * Returns the `train` flag from the API response.
     *
     * True means the API skipped real validation and forced `success` to true —
     * typically because the sitekey does not exist, the secret does not match,
     * or the sitekey's protection toggle is disabled. In production this means
     * every submission appears successful regardless of whether the user solved
     * the captcha. Check your sitekey and secret immediately if you see this.
     *
     * False means normal operation and `successToken()` reflects the real result.
     *
     * Null means no API response was received (network failure) so the train
     * state is unknown.
     */
    public function isTrain(): ?bool
    {
        return $this->stateTrain;
    }
}
