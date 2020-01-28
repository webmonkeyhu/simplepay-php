<?php

declare(strict_types=1);

namespace Webmonkey\SimplePay;

use Webmonkey\SimplePay\Traits\CommunicationTrait;
use Webmonkey\SimplePay\Traits\LoggerTrait;
use Webmonkey\SimplePay\Traits\SignatureTrait;
use Webmonkey\SimplePay\Traits\ViewsTrait;

use JsonSerializable;

use function array_merge;
use function end;
use function explode;
use function hash;
use function hash_file;
use function is_array;
use function is_object;
use function json_decode;
use function json_encode;
use function phpversion;
use function rand;
use function strlen;
use function strtoupper;
use function substr;
use function unserialize;

class Base
{
    use CommunicationTrait;
    use LoggerTrait;
    use SignatureTrait;
    use ViewsTrait;

    protected $phpVersion = 7;
    protected $headers    = [];
    protected $hashAlgo   = 'sha384';

    protected $logSeparator     = '|';
    protected $logContent       = [];
    protected $debugMessage     = [];
    protected $currentInterface = '';

    protected $api = [
        'sandbox' => 'https://sandbox.simplepay.hu/payment',
        'live'    => 'https://secure.simplepay.hu/payment',
    ];

    protected $apiInterface = [
        'start'  => '/v2/start',
        'finish' => '/v2/finish',
        'refund' => '/v2/refund',
        'query'  => '/v2/query',
    ];

    public $config          = [];
    public $transactionBase = [];
    public $sdkVersion      = 'Webmonkey_SimplePay_PHP_SDK_1.0.0_190924';

    public $logTransactionId = 'N/A';
    public $logOrderRef      = 'N/A';
    public $logPath          = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logContent['runMode'] = strtoupper($this->currentInterface);

        $ver = (float) phpversion();

        $this->logContent['PHP'] = $ver;

        if ($ver < 7) {
            $this->phpVersion = 5;
        }
    }

    /**
     * Add uniq config field
     *
     * @param string $key   Config field name
     * @param string $value Config field value
     */
    public function addConfigData(string $key = '', string $value = ''): void
    {
        if ($key === '') {
            $key = 'EMPTY_CONFIG_KEY';
        }

        $this->config[$key] = $value;
    }

    /**
     * Add complete config array
     *
     * @param array $config Populated config array
     */
    public function addConfig(array $config = []): void
    {
        foreach ($config as $configKey => $configValue) {
            $this->config[$configKey] = $configValue;
        }
    }

    /**
     * Add uniq transaction field
     *
     * @param string $key   Data field name
     * @param string $value Data field value
     */
    public function addData(string $key = '', string $value = ''): void
    {
        if ($key === '') {
            $key = 'EMPTY_DATA_KEY';
        }

        $this->transactionBase[$key] = $value;
    }

    /**
     * Add data to a group
     *
     * @param string $group Data group name
     * @param string $key   Data field name
     * @param string $value Data field value
     */
    public function addGroupData(string $group = '', string $key = '', string $value = ''): void
    {
        if (! isset($this->transactionBase[$group])) {
            $this->transactionBase[$group] = [];
        }

        $this->transactionBase[$group][$key] = $value;
    }

    /**
     * Add item to pay
     *
     * @param array $itemData A product or service for pay
     */
    public function addItems(array $itemData = []): void
    {
        $item = [
            'ref'         => '',
            'title'       => '',
            'description' => '',
            'amount'      => 0,
            'price'       => 0,
            'tax'         => 0,
        ];

        if (! isset($this->transactionBase['items'])) {
            $this->transactionBase['items'] = [];
        }

        foreach ($itemData as $itemKey => $itemValue) {
            $item[$itemKey] = $itemValue;
        }

        $this->transactionBase['items'][] = $item;
    }

    /**
     * Shows transaction base data
     *
     * @return array Transaction data
     */
    public function getTransactionBase(): array
    {
        return $this->transactionBase;
    }

    /**
     * Shows API call return data
     *
     * @return array Return data
     */
    public function getReturnData(): array
    {
        return $this->convertToArray($this->returnData);
    }

    /**
     * Shows transactional log
     *
     * @return array Transactional log
     */
    public function getLogContent(): array
    {
        return $this->logContent;
    }

    /**
     * Check data if JSON, or set data to JSON
     *
     * @param string|array|object|null $data Data
     * @return string|int|null JSON encoded data
     */
    public function checkOrSetToJson($data = '')
    {
        $json = '[]';

        if (is_object($data) || $data === '') {
            return json_encode([]);
        }

        if (is_array($data) || $data instanceof JsonSerializable) {
            return json_encode($data);
        }

        if ($data === null) {
            return $data;
        }

        // int or string

        if (@json_decode((string)$data) !== null) {
            $json = $data;
        }

        if (@unserialize((string)$data) !== false) {
            $json = json_encode($result);
        }

        return $json;
    }

    /**
     * Serves header array
     *
     * @param string $hash     Signature for validation
     * @param string $language Landuage of content
     * @return array Populated header array
     */
    protected function getHeaders(string $hash = '', string $language = 'en'): array
    {
        return [
            'Accept-language: ' . $language,
            'Content-type: application/json',
            'Signature: ' . $hash,
        ];
    }

    /**
     * Random string generation for salt
     *
     * @param int $length Lemgth of random string, default 32
     * @return string Random string
     */
    protected function getSalt(int $length = 32): string
    {
        $saltBase = '';
        $chars    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

        for ($i = 1; $i < $length; $i++) {
            $saltBase .= substr($chars, rand(1, strlen($chars)), 1);
        }

        return hash('md5', $saltBase);
    }

    /**
     * API URL settings depend on function
     */
    protected function setApiUrl(): void
    {
        $api = 'live';

        if (isset($this->config['api'])) {
            $api = $this->config['api'];
        }

        $this->config['apiUrl'] = $this->api[$api] . $this->apiInterface[$this->currentInterface];
    }

    /**
     * Convert object to array
     *
     * @param object|array|null $obj Object to transform
     * @return array Result array
     */
    protected function convertToArray($obj): array
    {
        if (is_object($obj)) {
            $obj = (array) $obj;
        }

        $new = $obj !== null ? $obj : [];

        if (is_array($obj)) {
            $new = [];

            foreach ($obj as $key => $val) {
                $new[$key] = $this->convertToArray($val);
            }
        }

        return $new;
    }

    /**
     * Creates a 1-dimension array from a 2-dimension one
     *
     * @param array $arrayForProcess Array to be processed
     * @return array Flat array
     */
    protected function getFlatArray(array $arrayForProcess = []): array
    {
        $array = $this->convertToArray($arrayForProcess);

        $return = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $subArray = $this->getFlatArray($value);

                foreach ($subArray as $subKey => $subValue) {
                    $return[$key . '_' . $subKey] = $subValue;
                }
            } elseif (! is_array($value)) {
                $return[$key] = $value;
            }
        }

        return $return;
    }

    /**
     * Set config variables
     */
    protected function setConfig(): void
    {
        if (isset($this->transactionBase['currency']) && $this->transactionBase['currency'] !== '') {
            $this->config['merchant']    = $this->config[$this->transactionBase['currency'] . '_MERCHANT'];
            $this->config['merchantKey'] = $this->config[$this->transactionBase['currency'] . '_SECRET_KEY'];
        } elseif (isset($this->config['merchantAccount'])) {
            foreach ($this->config as $configKey => $configValue) {
                if ($configValue === $this->config['merchantAccount']) {
                    $key = $configKey;
                    break;
                }
            }

            $this->transactionBase['currency'] = substr($key, 0, 3);

            $this->config['merchant']    = $this->config[$this->transactionBase['currency'] . '_MERCHANT'];
            $this->config['merchantKey'] = $this->config[$this->transactionBase['currency'] . '_SECRET_KEY'];
        }

        $this->config['api'] = 'live';
        if ($this->config['SANDBOX']) {
            $this->config['api'] = 'sandbox';
        }

        $this->config['logPath'] = 'log';
        if ($this->config['SANDBOX']) {
            $this->config['logPath'] = $this->config['LOG_PATH'];
        }
    }

    /**
     * Transaction preparation
     *
     * All settings before start transaction
     */
    protected function prepare(): void
    {
        $this->setConfig();

        $this->logContent['callState1'] = 'PREPARE';

        $this->setApiUrl();

        $this->transactionBase['merchant']   = $this->config['merchant'];
        $this->transactionBase['salt']       = $this->getSalt();
        $this->transactionBase['sdkVersion'] = $this->sdkVersion . ':' . hash_file('md5', __FILE__);

        $this->content    = $this->getHashBase($this->transactionBase);
        $this->logContent = array_merge($this->logContent, $this->transactionBase);

        $this->config['computedHash'] = $this->getSignature($this->config['merchantKey'], $this->content);

        $this->headers = $this->getHeaders($this->config['computedHash'], 'EN');
    }

    /**
     * Execute API call and returns with result
     */
    protected function execApiCall(): array
    {
        $this->prepare();

        $transaction = [];

        $this->logContent['callState2']    = 'RUN';
        $this->logContent['sendApiUrl']    = $this->config['apiUrl'];
        $this->logContent['sendContent']   = $this->content;
        $this->logContent['sendSignature'] = $this->config['computedHash'];

        $commRresult = $this->runCommunication($this->config['apiUrl'], $this->content, $this->headers);

        $this->logContent['callState3'] = 'RESPONSE';

        // call result
        $result                      = explode("\r\n", $commRresult);
        $transaction['responseBody'] = end($result);

        // signature
        foreach ($result as $resultItem) {
            $headerElement = explode(":", $resultItem);

            if (isset($headerElement[0]) && isset($headerElement[1])) {
                $header[$headerElement[0]] = $headerElement[1];
            }
        }

        $transaction['responseSignature'] = $this->getSignatureFromHeader($header);

        // check transaction validity
        $transaction['responseSignatureValid'] = false;

        if ($this->isCheckSignature($transaction['responseBody'], $transaction['responseSignature'])) {
            $transaction['responseSignatureValid'] = true;
        }

        // fill transaction data
        if (is_object(json_decode($transaction['responseBody']))) {
            foreach (json_decode($transaction['responseBody']) as $key => $value) {
                $transaction[$key] = $value;
            }
        }

        if (isset($transaction['transactionId'])) {
            $this->logTransactionId = $transaction['transactionId'];
        } elseif (isset($transaction['cardId'])) {
            $this->logTransactionId = $transaction['cardId'];
        }

        if (isset($transaction['orderRef'])) {
            $this->logOrderRef = $transaction['orderRef'];
        }

        $this->returnData = $transaction;
        $this->logContent = array_merge($this->logContent, $transaction);
        $this->logContent = array_merge($this->logContent, $this->getTransactionBase());
        $this->logContent = array_merge($this->logContent, $this->getReturnData());

        $this->writeLog();

        return $transaction;
    }
}
