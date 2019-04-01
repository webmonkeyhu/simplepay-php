<?php

declare(strict_types=1);

namespace Webmonkey\SimplePay;

use Webmonkey\SimplePay\SimpleTransaction;

use function count;
use function is_string;

/**
 * SimplePay Instant Refund Notification
 *
 * Sends Refund request via HTTP request
 *
 * @category SDK
 * @package  SimplePay_SDK
 * @author   SimplePay IT <itsupport@otpmobil.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.html  GNU GENERAL PUBLIC LICENSE (GPL V3.0)
 * @link     http://simplepartner.hu/online_fizetesi_szolgaltatas.html
 */
class SimpleIrn extends SimpleTransaction
{
    public $targetUrl = '';
    public $commMethod = 'irn';
    public $irnRequest = [];
    public $hashFields = [
        "MERCHANT",
        "ORDER_REF",
        "ORDER_AMOUNT",
        "ORDER_CURRENCY",
        "IRN_DATE",
        "AMOUNT"
    ];

    protected $validFields = [
        "MERCHANT" => ["type" => "single", "paramName" => "merchantId", "required" => true],
        "ORDER_REF" => ["type" => "single", "paramName" => "orderRef", "required" => true],
        "ORDER_AMOUNT" => ["type" => "single", "paramName" => "amount", "required" => true],
        "AMOUNT" => ["type" => "single", "paramName" => "amount", "required" => true],
        "ORDER_CURRENCY" => ["type" => "single", "paramName" => "currency", "required" => true],
        "IRN_DATE" => ["type" => "single", "paramName" => "irnDate", "required" => true],
    ];

    /**
     * Constructor of SimpleIrn class
     *
     * @param mixed  $config   Configuration array or filename
     * @param string $currency Transaction currency
     *
     * @return void
     */
    public function __construct($config = [], $currency = '')
    {
        $config = $this->merchantByCurrency($config, $currency);
        $this->setup($config);
        if (isset($this->debug_irn)) {
            $this->debug = $this->debug_irn;
        }
        $this->fieldData['MERCHANT'] = $this->merchantId;
        $this->targetUrl = $this->defaultsData['BASE_URL'] . $this->defaultsData['IRN_URL'];
    }

    /**
     * Creates associative array for the received data
     *
     * @param array $data Processed data
     *
     * @return void
     */
    protected function nameData($data = [])
    {
        return [
            "ORDER_REF" => (isset($data[0])) ? $data[0] : 'N/A',
            "RESPONSE_CODE" => (isset($data[1])) ? $data[1] : 'N/A',
            "RESPONSE_MSG" => (isset($data[2])) ? $data[2] : 'N/A',
            "IRN_DATE" => (isset($data[3])) ? $data[3] : 'N/A',
            "ORDER_HASH" => (isset($data[4])) ? $data[4] : 'N/A',
        ];
    }

    /**
     * Sends notification via cURL
     *
     * @param array $data (Optional) Data array to be sent
     *
     * @return array $this->nameData() Result
     */
    public function requestIrn($data = [])
    {
        if (count($data) == 0) {
            $this->errorMessage[] = 'IRN DATA: EMPTY';
            return $this->nameData();
        }
        $data['MERCHANT'] = $this->merchantId;
        $this->refnoext = $data['REFNOEXT'];
        unset($data['REFNOEXT']);

        foreach ($this->hashFields as $fieldKey) {
            $data2[$fieldKey] = $data[$fieldKey];
        }
        $irnHash = $this->createHashString($data2);
        $data2['ORDER_HASH'] = $irnHash;
        $this->irnRequest = $data2;
        $this->logFunc("IRN", $this->irnRequest, $this->refnoext);

        $result = $this->startRequest($this->targetUrl, $this->irnRequest, 'POST');
        $this->debugMessage[] = 'IRN RESULT: ' . $result;

        if (is_string($result)) {
            $processed = $this->processResponse($result);
            $this->logFunc("IRN", $processed, $this->refnoext);
            return $processed;
        }
        $this->debugMessage[] = 'IRN RESULT: NOT STRING';
        return false;
    }
}
