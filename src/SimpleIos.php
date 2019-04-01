<?php

declare(strict_types=1);

namespace Webmonkey\SimplePay;

use Webmonkey\SimplePay\SimpleTransaction;

use function sleep;
use function simplexml_load_string;
use function date;
use function time;

/**
 * SimpleIOS
 *
 * Helper object containing information about a product
 *
 * @category SDK
 * @package  SimplePay_SDK
 * @author   SimplePay IT <itsupport@otpmobil.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.html  GNU GENERAL PUBLIC LICENSE (GPL V3.0)
 * @link     http://simplepartner.hu/online_fizetesi_szolgaltatas.html
 */
class SimpleIos extends SimpleTransaction
{
    protected $orderNumber;
    protected $merchantId;
    protected $orderStatus;
    protected $maxRun = 10;
    protected $iosOrderUrl = '';
    public $commMethod = 'ios';
    public $status = [];
    public $errorMessage = [];
    public $debugMessage = [];

    /**
     * Constructor of SimpleIos class
     *
     * @param array  $config      Configuration array or filename
     * @param string $currency    Transaction currency
     * @param string $orderNumber External number of the order
     *
     * @return void
     */
    public function __construct($config = [], $currency = '', $orderNumber = 'N/A')
    {
        $config = $this->merchantByCurrency($config, $currency);
        $this->setup($config);
        if (isset($this->debug_ios)) {
            $this->debug = $this->debug_ios;
        }
        $this->orderNumber = $orderNumber;
        $this->iosOrderUrl = $this->defaultsData['BASE_URL'] . $this->defaultsData['IOS_URL'];
        $this->runIos();
        $this->logFunc("IOS", $this->status, $this->orderNumber);
    }

    /**
     * Starts IOS communication
     *
     * @return void
     */
    public function runIos()
    {
        $this->debugMessage[] = 'IOS: START';
        if ($this->merchantId == "" || $this->orderNumber == 'N/A') {
            $this->errorMessage[] = 'IOS: MISSING DATA';
            $this->debugMessage[] = 'IOS: END';
            return false;
        }
        $iosArray = array(
            'MERCHANT' => $this->merchantId,
            'REFNOEXT' => $this->orderNumber,
            'HASH' => $this->createHashString(array($this->merchantId, $this->orderNumber))
        );
        $this->logFunc("IOS", $iosArray, $this->orderNumber);
        $iosCounter = 0;
        while ($iosCounter < $this->maxRun) {
            $result = $this->startRequest($this->iosOrderUrl, $iosArray, 'POST');
            if ($result === false) {
                $result = '<?xml version="1.0"?>
                <Order>
                    <ORDER_DATE>' . @date("Y-m-d H:i:s", time()) . '</ORDER_DATE>
                    <REFNO>N/A</REFNO>
                    <REFNOEXT>N/A</REFNOEXT>
                    <ORDER_STATUS>EMPTY RESULT</ORDER_STATUS>
                    <PAYMETHOD>N/A</PAYMETHOD>
                    <HASH>N/A</HASH>
                </Order>';
            }

            $resultArray = (array) simplexml_load_string($result);
            foreach ($resultArray as $itemName => $itemValue) {
                $this->status[$itemName] = $itemValue;
            }

            //Validation
            $valid = false;
            if (!isset($this->status['HASH'])) {
                $this->debugMessage[] = 'IOS HASH: MISSING';
            }
            if ($this->createHashString($this->flatArray($this->status, ["HASH"])) == @$this->status['HASH']) {
                $valid = true;
                $this->debugMessage[] = 'IOS HASH: VALID';
            }
            if (!$valid) {
                $iosCounter += $this->maxRun+10;
                $this->debugMessage[] = 'IOS HASH: INVALID';
            }

            //state
            switch ($this->status['ORDER_STATUS']) {
            case 'NOT_FOUND':
                $iosCounter++;
                sleep(1);
                break;
            case 'CARD_NOTAUTHORIZED':
                $iosCounter += 5;
                sleep(1);
                break;
            default:
                $iosCounter += $this->maxRun;
            }
            $this->debugMessage[] = 'IOS ORDER_STATUS: ' . $this->status['ORDER_STATUS'];
        }
        $this->debugMessage[] = 'IOS: END';
    }
}
