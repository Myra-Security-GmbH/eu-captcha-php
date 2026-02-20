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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Myrasec\EuCaptcha;
use Myrasec\Exception\EuCaptchaException;
use PHPUnit\Framework\TestCase;

class EuCaptchaTest extends TestCase
{
    private function makeClient(array $responses): Client
    {
        $mock  = new MockHandler($responses);
        $stack = HandlerStack::create($mock);

        return new Client(['handler' => $stack]);
    }

    /** @param array<int, array<string, mixed>> $container Passed by reference; filled with request/response pairs. */
    private function makeCapturingClient(array $responses, array &$container): Client
    {
        $mock  = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($container));

        return new Client(['handler' => $stack]);
    }

    private function capturedRemoteIp(array $container): string
    {
        $body = json_decode((string) $container[0]['request']->getBody(), true);

        return $body['remoteip'] ?? '';
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testConstructorThrowsOnEmptySitekey(): void
    {
        $this->expectException(EuCaptchaException::class);

        new EuCaptcha(sitekey: '', secret: 'secret');
    }

    public function testConstructorThrowsOnEmptySecret(): void
    {
        $this->expectException(EuCaptchaException::class);

        new EuCaptcha(sitekey: 'sitekey', secret: '');
    }

    public function testConstructorSucceedsWithValidCredentials(): void
    {
        $captcha = new EuCaptcha(sitekey: 'sitekey', secret: 'secret');

        $this->assertInstanceOf(EuCaptcha::class, $captcha);
    }

    // -------------------------------------------------------------------------
    // validate()
    // -------------------------------------------------------------------------

    public function testValidateSuccessWhenTokenIsValid(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['success' => true])),
        ]);

        $result = (new EuCaptcha(sitekey: 'sk', secret: 'sec', client: $client))
            ->validate('test-token', '127.0.0.1');

        $this->assertTrue($result->success());
        $this->assertTrue($result->successNetwork());
        $this->assertTrue($result->successToken());
    }

    public function testValidateFailsWhenTokenIsInvalid(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['success' => false])),
        ]);

        $result = (new EuCaptcha(sitekey: 'sk', secret: 'sec', client: $client))
            ->validate('bad-token', '127.0.0.1');

        $this->assertFalse($result->success());
        $this->assertTrue($result->successNetwork());
        $this->assertFalse($result->successToken());
    }

    public function testValidateOnNetworkFailureWithFailDefaultTrue(): void
    {
        $client = $this->makeClient([
            new ConnectException('Connection refused', new Request('POST', 'test')),
        ]);

        $result = (new EuCaptcha(sitekey: 'sk', secret: 'sec', failDefault: true, client: $client))
            ->validate('token', '127.0.0.1');

        $this->assertTrue($result->successNetwork());
        $this->assertTrue($result->successToken());
        $this->assertTrue($result->success());
    }

    public function testValidateOnNetworkFailureWithFailDefaultFalse(): void
    {
        $client = $this->makeClient([
            new ConnectException('Connection refused', new Request('POST', 'test')),
        ]);

        $result = (new EuCaptcha(sitekey: 'sk', secret: 'sec', failDefault: false, client: $client))
            ->validate('token', '127.0.0.1');

        $this->assertFalse($result->successNetwork());
        $this->assertFalse($result->successToken());
        $this->assertFalse($result->success());
    }

    public function testValidateReadsTokenFromPost(): void
    {
        $_POST['eu-captcha-response'] = 'post-token';

        $client = $this->makeClient([
            new Response(200, [], json_encode(['success' => true])),
        ]);

        $result = (new EuCaptcha(sitekey: 'sk', secret: 'sec', client: $client))
            ->validate(remoteAddr: '1.2.3.4');

        $this->assertTrue($result->success());

        unset($_POST['eu-captcha-response']);
    }

    public function testValidateReadsRemoteAddrFromRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $client = $this->makeClient([
            new Response(200, [], json_encode(['success' => true])),
        ]);

        $result = (new EuCaptcha(sitekey: 'sk', secret: 'sec', client: $client))
            ->validate('token');

        $this->assertTrue($result->success());

        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testValidatePrefersXForwardedForOverRemoteAddr(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5';
        $_SERVER['REMOTE_ADDR']          = '10.0.0.1';

        $client = $this->makeClient([
            new Response(200, [], json_encode(['success' => true])),
        ]);

        $result = (new EuCaptcha(sitekey: 'sk', secret: 'sec', client: $client))
            ->validate('token');

        $this->assertTrue($result->success());

        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);
    }

    // -------------------------------------------------------------------------
    // verifyCredentials()
    // -------------------------------------------------------------------------

    public function testVerifyCredentialsReturnsTrueWhenValid(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['valid' => true])),
        ]);

        $this->assertTrue(
            (new EuCaptcha(sitekey: 'sk', secret: 'sec', client: $client))->verifyCredentials()
        );
    }

    public function testVerifyCredentialsReturnsFalseWhenInvalid(): void
    {
        $client = $this->makeClient([
            new Response(200, [], json_encode(['valid' => false])),
        ]);

        $this->assertFalse(
            (new EuCaptcha(sitekey: 'sk', secret: 'sec', client: $client))->verifyCredentials()
        );
    }

    public function testVerifyCredentialsReturnsFalseOnNetworkError(): void
    {
        $client = $this->makeClient([
            new ConnectException('Connection refused', new Request('POST', 'test')),
        ]);

        $this->assertFalse(
            (new EuCaptcha(sitekey: 'sk', secret: 'sec', client: $client))->verifyCredentials()
        );
    }

    // -------------------------------------------------------------------------
    // resolveClientIp() / checkCdnHeaders
    // -------------------------------------------------------------------------

    public function testCheckCdnHeadersTrueUsesHttpClientIp(): void
    {
        $_SERVER['HTTP_CLIENT_IP']       = '203.0.113.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.2';
        $_SERVER['REMOTE_ADDR']          = '10.0.0.1';

        $container = [];
        $client    = $this->makeCapturingClient([new Response(200, [], json_encode(['success' => true]))], $container);

        (new EuCaptcha(sitekey: 'sk', secret: 'sec', client: $client))->validate('token');

        $this->assertSame('203.0.113.1', $this->capturedRemoteIp($container));

        unset($_SERVER['HTTP_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);
    }

    public function testCheckCdnHeadersTrueUsesXForwardedForWhenNoClientIp(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.2';
        $_SERVER['REMOTE_ADDR']          = '10.0.0.1';

        $container = [];
        $client    = $this->makeCapturingClient([new Response(200, [], json_encode(['success' => true]))], $container);

        (new EuCaptcha(sitekey: 'sk', secret: 'sec', client: $client))->validate('token');

        $this->assertSame('203.0.113.2', $this->capturedRemoteIp($container));

        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);
    }

    public function testCheckCdnHeadersTrueUsesFirstEntryOfXForwardedFor(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.10, 203.0.113.20, 10.0.0.1';
        $_SERVER['REMOTE_ADDR']          = '10.0.0.1';

        $container = [];
        $client    = $this->makeCapturingClient([new Response(200, [], json_encode(['success' => true]))], $container);

        (new EuCaptcha(sitekey: 'sk', secret: 'sec', client: $client))->validate('token');

        $this->assertSame('203.0.113.10', $this->capturedRemoteIp($container));

        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);
    }

    public function testCheckCdnHeadersTrueUsesXRealIpAsFallback(): void
    {
        $_SERVER['HTTP_X_REAL_IP'] = '203.0.113.3';
        $_SERVER['REMOTE_ADDR']    = '10.0.0.1';

        $container = [];
        $client    = $this->makeCapturingClient([new Response(200, [], json_encode(['success' => true]))], $container);

        (new EuCaptcha(sitekey: 'sk', secret: 'sec', client: $client))->validate('token');

        $this->assertSame('203.0.113.3', $this->capturedRemoteIp($container));

        unset($_SERVER['HTTP_X_REAL_IP'], $_SERVER['REMOTE_ADDR']);
    }

    public function testCheckCdnHeadersFalseIgnoresProxyHeadersAndUsesRemoteAddr(): void
    {
        $_SERVER['HTTP_CLIENT_IP']       = '203.0.113.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.2';
        $_SERVER['HTTP_X_REAL_IP']       = '203.0.113.3';
        $_SERVER['REMOTE_ADDR']          = '10.0.0.1';

        $container = [];
        $client    = $this->makeCapturingClient([new Response(200, [], json_encode(['success' => true]))], $container);

        (new EuCaptcha(sitekey: 'sk', secret: 'sec', checkCdnHeaders: false, client: $client))->validate('token');

        $this->assertSame('10.0.0.1', $this->capturedRemoteIp($container));

        unset($_SERVER['HTTP_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP'], $_SERVER['REMOTE_ADDR']);
    }
}
