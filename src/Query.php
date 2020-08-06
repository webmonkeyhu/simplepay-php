<?php

declare(strict_types=1);

namespace Webmonkey\SimplePay;

use function count;

final class Query extends Base
{
    protected $currentInterface = 'query';
    protected $returnData       = [];

    public $transactionBase = [
        'salt'     => '',
        'merchant' => '',
        'currency' => '',
    ];

    /**
     * Add SimplePay transaction ID to query
     *
     * @param string $simplePayId SimplePay transaction ID
     */
    public function addSimplePayId(string $simplePayId = ''): void
    {
        if (
            ! isset($this->transactionBase['transactionIds']) ||
            count($this->transactionBase['transactionIds']) === 0
        ) {
            $this->logTransactionId = $simplePayId;
        }

        $this->transactionBase['transactionIds'][] = $simplePayId;
    }

    /**
     * Add merchant order ID to query
     *
     * @param string $merchantOrderId Merchant order ID
     */
    public function addMerchantOrderId(string $merchantOrderId = ''): void
    {
        if (
            ! isset($this->transactionBase['orderRefs']) ||
            count($this->transactionBase['orderRefs']) === 0
        ) {
            $this->logOrderRef = $merchantOrderId;
        }

        $this->transactionBase['orderRefs'][] = $merchantOrderId;
    }

    /**
     * Run transaction data query
     *
     * @return array API response
     */
    public function runQuery(): array
    {
        return $this->execApiCall();
    }
}
