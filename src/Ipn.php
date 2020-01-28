<?php

declare(strict_types=1);

namespace Webmonkey\SimplePay;

use Exception;

use function date;
use function header;
use function json_decode;
use function json_encode;
use function str_replace;
use function strtolower;
use function substr;
use function time;
use function ucwords;

final class Ipn extends Base
{
    protected $currentInterface = 'ipn';
    protected $returnData       = [];
    protected $receiveDate      = '';
    protected $ipnContent       = [];
    protected $responseContent  = '';
    protected $ipnReturnData    = [];

    public $validationResult = false;

    /**
     * IPN validation
     *
     * @param string $content IPN content
     */
    public function isIpnSignatureCheck(string $content = ''): bool
    {
        $signature = $this->getSignatureFromHeader($this->getAllHeaders());

        foreach (json_decode($this->checkOrSetToJson($content)) as $key => $value) {
            $this->ipnContent[$key] = $value;
        }

        if (isset($this->ipnContent['merchant'])) {
            $this->addConfigData('merchantAccount', $this->ipnContent['merchant']);
        }

        $this->setConfig();

        $this->validationResult = false;

        if ($this->isCheckSignature($content, $signature)) {
            $this->validationResult = true;
        }

        $this->logContent['ipnBodyToValidation'] = $content;

        $this->logTransactionId = $this->ipnContent['transactionId'];
        $this->logOrderRef      = $this->ipnContent['orderRef'];

        $this->writeLog([
            'validationResult' => $this->validationResult,
        ]);

        foreach ($this->ipnContent as $contentKey => $contentValue) {
            $this->logContent[$contentKey] = $contentValue;
        }

        $this->logContent['validationResult'] = $this->validationResult;

        if (! $this->validationResult) {
            $this->logContent['validationResultMessage'] = 'UNSUCCESSFUL VALIDATION, NO CONFIRMATION';
        }

        $this->writeLog($this->logContent);

        // confirm setup
        if (! $this->validationResult) {
            $this->confirmContent = 'UNSUCCESSFUL VALIDATION';
            $this->signature      = 'UNSUCCESSFUL VALIDATION';
        } elseif ($this->validationResult) {
            $this->ipnContent['receiveDate'] = @date("c", time());

            $this->confirmContent = json_encode($this->ipnContent);
            $this->signature      = $this->getSignature($this->config['merchantKey'], $this->confirmContent);
        }

        $this->ipnReturnData['signature']      = $this->signature;
        $this->ipnReturnData['confirmContent'] = $this->confirmContent;

        $this->writeLog([
            'confirmSignature' => $this->signature,
            'confirmContent'   => $this->confirmContent,
        ]);

        return $this->validationResult;
    }

    /**
     * Immediate IPN confirmation
     */
    public function runIpnConfirm(): bool
    {
        try {
            header('Accept-language: EN');
            header('Content-type: application/json');
            header('Signature: ' . $this->ipnReturnData['signature']);

            print $this->ipnReturnData['confirmContent'];
        } catch (Exception $e) {
            $this->writeLog([
                'ipnConfirm' => $e->getMessage(),
            ]);

            return false;
        }

        $this->writeLog([
            'ipnConfirm' => 'Confirmed directly by runIpnConfirm',
        ]);

        return true;
    }

    /**
     * IPN confirmation data
     *
     * @return array Content and signature for mercaht system
     */
    public function getIpnConfirmContent(): array
    {
        $this->writeLog([
            'ipnConfirm' => 'ipnReturnData provided as content by getIpnConfirmContent',
        ]);

        return $this->ipnReturnData;
    }

    /**
     * Private polyfill 'getallheaders' for Nginx and Apache
     *
     * @return array Server headers
     */
    private function getAllHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }

        return $headers;
    }
}
