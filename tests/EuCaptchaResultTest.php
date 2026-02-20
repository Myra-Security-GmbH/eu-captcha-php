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

namespace Myrasec\Tests;

use Myrasec\EuCaptchaResult;
use PHPUnit\Framework\TestCase;

class EuCaptchaResultTest extends TestCase
{
    public function testSuccessReturnsTrueWhenBothStatesAreTrue(): void
    {
        $result = new EuCaptchaResult(stateNetwork: true, stateToken: true);

        $this->assertTrue($result->success());
    }

    public function testSuccessReturnsFalseWhenNetworkFailed(): void
    {
        $result = new EuCaptchaResult(stateNetwork: false, stateToken: true);

        $this->assertFalse($result->success());
    }

    public function testSuccessReturnsFalseWhenTokenInvalid(): void
    {
        $result = new EuCaptchaResult(stateNetwork: true, stateToken: false);

        $this->assertFalse($result->success());
    }

    public function testSuccessReturnsFalseWhenBothStatesFalse(): void
    {
        $result = new EuCaptchaResult(stateNetwork: false, stateToken: false);

        $this->assertFalse($result->success());
    }

    public function testSuccessNetworkReflectsNetworkState(): void
    {
        $this->assertTrue((new EuCaptchaResult(stateNetwork: true, stateToken: false))->successNetwork());
        $this->assertFalse((new EuCaptchaResult(stateNetwork: false, stateToken: true))->successNetwork());
    }

    public function testSuccessTokenReflectsTokenState(): void
    {
        $this->assertTrue((new EuCaptchaResult(stateNetwork: false, stateToken: true))->successToken());
        $this->assertFalse((new EuCaptchaResult(stateNetwork: true, stateToken: false))->successToken());
    }
}
