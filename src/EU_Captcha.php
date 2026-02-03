<?php

/*
 * Copyright (c) 2026 Myra Security GmbH
 *
 * See LICENSE for license.
 */

namespace Myrasec;

class EU_Captcha
{
    protected $sitekey;
    protected $secret;
    protected $verifyUrl;

    /**
     * set this to "false" via options to fail the test
     * if network or API issues prevent us from getting
     * a valid check result.
     *
     * With the default value of "true", any problem
     * communicating with the API will result in a
     * successful output (fail-safe).
     */
    protected $failDefault = true;

    public function __construct(array $options = [])
    {
        if (!isset($options['sitekey'])) {
            throw new Exception("missing option sitekey");
        }

        if (!isset($options['secret'])) {
            throw new Exception("missing option secret");
        }

        $this->sitekey   = $options['sitekey'];
        $this->secret    = $options['secret'];
        $this->verifyUrl = 'https://api.eu-captcha.eu/v1/verify/';

        if (isset($options["failDefault"])) {
            $this->failDefault = $options["failDefault"];
        }
    }

    protected function doApiCall($url, $data)
    {

        $options = [
            'http' => [
                'method'  => 'POST',
                'content' => json_encode($data),
                'header'  => 'Content-Type: application/json',
            ]
        ];

        $context = stream_context_create($options);

        $json = file_get_contents($url, false, $context);

        return $json;
    }

    public function validate($token = null, $remote_addr = "")
    {
        if (is_null($token)) {
            if (isset($_POST["eu-captcha-response"])) {
                $token = $_POST["eu-captcha-response"];
            } else {
                /* if we cannot find token, the client might not have submitted it.
                 * we still need to call the API to properly count attempts.
                 */
                $token = "";
            }
        }

        if (is_null($remote_addr)) {
            /* XXX should be configurable */
            if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
                $remote_addr = $_SERVER["HTTP_X_FORWARDED_FOR"];
            } else if (isset($_SERVER["REMOTE_ADDR"])) {
                $remote_addr = $_SERVER["REMOTE_ADDR"];
            } else {
                $remote_addr = "";
            }
        }

        $data = [
            'sitekey'  => $this->sitekey,
            'secret'   => $this->secret,
            'remote'   => $remote_addr,
            'response' => $token,
        ];

        $json = $this->doApiCall($this->verifyUrl, $data);

        if ($json === false) {
            return new EU_Captcha_Result($this->failDefault, $this->failDefault);
        }

        $decode = json_decode($json, true);

        if (!is_array($decode) || !isset($decode["success"])) {
            return new EU_Captcha_Result($this->failDefault, $this->failDefault);
        }

        $ret = new EU_Captcha_Result(true, $decode["success"]);

        return $ret;
    }
}
