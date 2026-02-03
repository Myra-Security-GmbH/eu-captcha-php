<?php

/*
 * Copyright (c) 2026 Myra Security GmbH
 *
 * See LICENSE for license.
 */

namespace Myrasec;

class EU_Captcha_Result
{
    protected $stateNetwork;
    protected $stateToken;

    public function __construct($stateNetwork, $stateToken)
    {
        $this->stateNetwork = $stateNetwork;
        $this->stateToken   = $stateToken;
    }

    public function success() {
        return ($this->stateNetwork && $this->stateToken);
    }

    public function successNetwork() {
        return $this->stateNetwork;
    }

    public function successToken() {
        return $this->stateToken;
    }
}
