<?php

declare(strict_types=1);

namespace Webmonkey\SimplePay;

final class Finish extends Base
{
    protected $currentInterface = 'finish';
    protected $returnData       = [];

    public $transactionBase = [
        'salt'          => '',
        'merchant'      => '',
        'orderRef'      => '',
        'transactionId' => '',
        'originalTotal' => '',
        'approveTotal'  => '',
        'currency'      => '',
    ];

    /**
     * Run finish
     *
     * @return array API response
     */
    public function runFinish(): array
    {
        return $this->execApiCall();
    }
}
