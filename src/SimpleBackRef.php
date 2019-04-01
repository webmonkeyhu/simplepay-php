<?php

declare(strict_types=1);

namespace Webmonkey\SimplePay;

use Webmonkey\SimplePay\SimpleTransaction;
use Webmonkey\SimplePay\SimpleIos;

use function substr;
use function strlen;
use function is_object;
use function in_array;
use function trim;

/**
 * SimplePay BACK_REF
 *
 * Processes information sent via HTTP GET on the returning site after a payment
 *
 * @category SDK
 * @package  SimplePay_SDK
 * @author   SimplePay IT <itsupport@otpmobil.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.html  GNU GENERAL PUBLIC LICENSE (GPL V3.0)
 * @link     http://simplepartner.hu/online_fizetesi_szolgaltatas.html
 */
class SimpleBackRef extends SimpleTransaction
{
    protected $backref;
    public $commMethod = 'backref';
    public $protocol;
    protected $request;
    protected $returnVars = [
        "RC",
        "RT",
        "3dsecure",
        "date",
        "payrefno",
        "ctrl"
    ];
    public $backStatusArray = [
        'BACKREF_DATE' => 'N/A',
        'REFNOEXT' => 'N/A',
        'PAYREFNO' => 'N/A',
        'ORDER_STATUS' => 'N/A',
        'PAYMETHOD' => 'N/A',
        'RESULT' => false
    ];
    public $successfulStatus = [
        "IN_PROGRESS",          //card authorized on backref
        "PAYMENT_AUTHORIZED",   //IPN
        "COMPLETE",             //IDN
        "WAITING_PAYMENT",      //waiting for WIRE
    ];
    public $unsuccessfulStatus = [
        "CARD_NOTAUTHORIZED",   //unsuccessful transaction
        "FRAUD",
        "TEST",
        "TIMEOUT"
    ];

    /**
     * Constructor of SimpleBackRef class
     *
     * @param array  $config   Configuration array or filename
     * @param string $currency Transaction currency
     *
     * @return void
     */
    public function __construct($config = array(), $currency = '')
    {
        $config = $this->merchantByCurrency($config, $currency);
        $this->iosConfig = $config;
        $this->setup($config);
        if (isset($this->debug_backref)) {
            $this->debug = $this->debug_backref;
        }
        $this->createRequestUri();
        $this->backStatusArray['BACKREF_DATE'] = (isset($this->getData['date'])) ? $this->getData['date'] : 'N/A';
        $this->backStatusArray['REFNOEXT'] = (isset($this->getData['order_ref'])) ? $this->getData['order_ref'] : 'N/A';
        $this->backStatusArray['PAYREFNO'] = (isset($this->getData['payrefno'])) ? $this->getData['payrefno'] : 'N/A';
    }

    /**
     * Creates request URI from HTTP SERVER VARS.
     * Handles http and https
     *
     * @return void
     */
    protected function createRequestUri()
    {
        if ($this->protocol == '') {
            $this->protocol = "http";
        }
        $this->request = $this->protocol . '://' . $this->serverData['HTTP_HOST'] . $this->serverData['REQUEST_URI'];
        $this->debugMessage[] = 'REQUEST: ' . $this->request;
    }

    /**
     * Validates CTRL variable
     *
     * @return boolean
     */
    protected function checkCtrl()
    {
        $requestURL = substr($this->request, 0, -38); //the last 38 characters are the CTRL param
        $hashInput = strlen($requestURL) . $requestURL;
        $this->debugMessage[] = 'REQUEST URL: ' . $requestURL;
        $this->debugMessage[] = 'GET ctrl: ' . @$this->getData['ctrl'];
        $this->debugMessage[] = 'Calculated ctrl: ' . $this->hmac($this->secretKey, $hashInput);
        if (isset($this->getData['ctrl']) && $this->getData['ctrl'] == $this->hmac($this->secretKey, $hashInput)) {
            return true;
        }
        $this->errorMessage[] = 'HASH: Calculated hash is not valid!';
        $this->errorMessage[] = 'BACKREF ERROR: ' . @$this->getData['err'];
        return false;
    }

    /**
     * Check card authorization response
     *
     * 1. check ctrl
     * 2. check RC & RT
     * 3. check IOS status
     *
     * @return boolean
     */
    public function checkResponse()
    {
        if (!isset($this->order_ref)) {
            $this->errorMessage[] = 'CHECK RESPONSE: Missing order_ref variable!';
            return false;
        }
        $this->logFunc("BackRef", $this->getData, $this->order_ref);

        if (!$this->checkCtrl()) {
            $this->errorMessage[] = 'CHECK RESPONSE: INVALID CTRL!';
            return false;
        }

        $ios = new SimpleIos($this->iosConfig, $this->getData['order_currency'], $this->order_ref);

        foreach ($ios->errorMessage as $msg) {
            $this->errorMessage[] = $msg;
        }
        foreach ($ios->debugMessage as $msg) {
            $this->debugMessage[] = $msg;
        }

        if (is_object($ios)) {
            $this->checkIOSStatus($ios);
        }
        $this->logFunc("BackRef_BackStatus", $this->backStatusArray, $this->order_ref);
        if (!$this->checkRtVariable($ios)) {
            return false;
        }
        if (!$this->backStatusArray['RESULT']) {
            return false;
        }
        return true;
    }

    /**
     * Check IOS result
     *
     * @param obj $ios Result of IOS comunication
     *
     * @return boolean
     */
    protected function checkIOSStatus($ios)
    {
        $this->backStatusArray['ORDER_STATUS'] = (isset($ios->status['ORDER_STATUS'])) ? $ios->status['ORDER_STATUS'] : 'IOS_ERROR';
        $this->backStatusArray['PAYMETHOD'] = (isset($ios->status['PAYMETHOD'])) ? $ios->status['PAYMETHOD'] : 'N/A';
        if (in_array(trim($ios->status['ORDER_STATUS']), $this->successfulStatus)) {
            $this->backStatusArray['RESULT'] = true;
        } elseif (in_array(trim($ios->status['ORDER_STATUS']), $this->unsuccessfulStatus)) {
            $this->backStatusArray['RESULT'] = false;
            $this->errorMessage[] = 'IOS STATUS: UNSUCCESSFUL!';
        }
    }

    /**
     * Check RT variable
     *
     * @param obj $ios Result of IOS comunication
     *
     * @return boolean
     */
    protected function checkRtVariable($ios)
    {
        $successCode = array("000", "001");
        if (isset($this->getData['RT'])) {
            $returnCode = substr($this->getData['RT'], 0, 3);

            //000 and 001 are successful
            if (in_array($returnCode, $successCode)) {
                $this->backStatusArray['RESULT'] = true;
                $this->debugMessage[] = 'Return code: ' . $returnCode;
                return true;

                //all other unsuccessful
            } elseif ($this->getData['RT'] != "" && !in_array($returnCode, $successCode)) {
                $this->backStatusArray['RESULT'] = false;
                $this->errorMessage[] = 'Return code: ' . $returnCode;
                return false;

                //in case of WIRE we have no bank return code and return text value, so RT has to be empty
            } elseif ($this->getData['RT'] == "") {
                //check IOS ORDER_STATUS
                if (in_array(trim($ios->status['ORDER_STATUS']), $this->successfulStatus)) {
                    $this->backStatusArray['RESULT'] = true;
                    return true;
                }
                $this->backStatusArray['RESULT'] = false;
                $this->errorMessage[] = 'Empty RT and status is: ' . $ios->status['ORDER_STATUS'];
                return false;
            }
            $this->backStatusArray['RESULT'] = false;
            $this->errorMessage[] = 'RT content: failure!';
            return false;

        } elseif (!isset($this->getData['RT'])) {
            $this->backStatusArray['RESULT'] = false;
            $this->errorMessage[] = 'Missing variable: (RT)!';
            return false;
        }
        $this->backStatusArray['RESULT'] = false;
        $this->errorMessage[] = 'checkRtVariable: general error!';
        return false;
    }
}
