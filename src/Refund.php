<?php

declare(strict_types=1);

namespace Webmonkey\SimplePay;

final class Refund extends Base
{
    protected $currentInterface = 'refund';
    protected $returnData       = [];

    public $transactionBase = [
        'salt'          => '',
        'merchant'      => '',
        'orderRef'      => '',
        'transactionId' => '',
        'currency'      => '',
    ];

    /**
     * Run refund
     *
     * @return array API response
     */
    public function runRefund(): array
    {
        if ($this->transactionBase['orderRef'] === '') {
            unset($this->transactionBase['orderRef']);
        }

        if ($this->transactionBase['transactionId'] === '') {
            unset($this->transactionBase['transactionId']);
        }

        $this->logTransactionId = @$this->transactionBase['transactionId'];
        $this->logOrderRef      = @$this->transactionBase['orderRef'];

        return $this->execApiCall();
    }
}
