<?php

declare(strict_types=1);

namespace Webmonkey\SimplePay;

final class Start extends Base
{
    protected $currentInterface = 'start';

    public $transactionBase = [
        'salt'          => '',
        'merchant'      => '',
        'orderRef'      => '',
        'currency'      => '',
        'customerEmail' => '',
        'language'      => '',
        'sdkVersion'    => '',
        'methods'       => [],
    ];

    /**
     * Send initial data to SimplePay API for validation
     * The result is the payment link to where website has to redirect customer
     */
    public function runStart(): void
    {
        $this->execApiCall();
    }
}
