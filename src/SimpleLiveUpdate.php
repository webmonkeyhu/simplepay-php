<?php

declare(strict_types=1);

namespace Webmonkey\SimplePay;

use Webmonkey\SimplePay\SimpleTransaction;

use function addslashes;
use function is_array;
use function count;
use function strpos;

 /**
  * SimplePay LiveUpdate
  *
  * Sending orders via HTTP request
  *
  * @category SDK
  * @package  SimplePay_SDK
  * @author   SimplePay IT <itsupport@otpmobil.com>
  * @license  http://www.gnu.org/licenses/gpl-3.0.html  GNU GENERAL PUBLIC LICENSE (GPL V3.0)
  * @link     http://simplepartner.hu/online_fizetesi_szolgaltatas.html
  */
class SimpleLiveUpdate extends SimpleTransaction
{
    public $formData = [];
    public $commMethod = 'liveupdate';
    protected $hashData = [];
    protected $validFields = [
        //order
        "MERCHANT" => ["type" => "single", "paramName" => "merchantId", "required" => true],
        "ORDER_REF" => ["type" => "single", "required" => true],
        "ORDER_DATE" => ["type" => "single", "required" => true],
        "ORDER_PNAME" => ["type" => "product", "paramName" => "name"],
        "ORDER_PCODE" => ["type" => "product", "paramName" => "code"],
        "ORDER_PINFO" => ["type" => "product", "paramName" => "info"],
        "ORDER_PRICE" => ["type" => "product", "paramName" => "price", "required" => true],
        "ORDER_QTY" => ["type" => "product", "paramName" => "qty", "required" => true],
        "ORDER_VAT" => ["type" => "product", "default" => "0", "paramName" => "vat", "required" => true],
        "PRICES_CURRENCY" => ["type" => "single", "default" => "HUF", "required" => true],
        "ORDER_SHIPPING" => ["type" => "single", "default" => "0"],
        "DISCOUNT" => ["type" => "single", "default" => "0"],
        "PAY_METHOD" => ["type" => "single", "default" => "CCVISAMC", "required" => true],
        "LANGUAGE" => ["type" => "single", "default" => "HU"],
        "ORDER_TIMEOUT" => ["type" => "single", "default" => "300"],
        "TIMEOUT_URL" => ["type" => "single", "required" => true],
        "BACK_REF" => ["type" => "single", "required" => true],
        "LU_ENABLE_TOKEN" => ["type" => "single", "required" => false],
        "LU_TOKEN_TYPE" => ["type" => "single", "required" => false],

        //billing
        "BILL_FNAME" => ["type" => "single", "required" => true],
        "BILL_LNAME" => ["type" => "single", "required" => true],
        "BILL_COMPANY" => ["type" => "single"],
        "BILL_FISCALCODE" => ["type" => "single"],
        "BILL_EMAIL" => ["type" => "single", "required" => true],
        "BILL_PHONE" => ["type" => "single", "required" => true],
        "BILL_FAX" => ["type" => "single"],
        "BILL_ADDRESS" => ["type" => "single", "required" => true],
        "BILL_ADDRESS2" => ["type" => "single"],
        "BILL_ZIPCODE" => ["type" => "single", "required" => true],
        "BILL_CITY" => ["type" => "single", "required" => true],
        "BILL_STATE" => ["type" => "single", "required" => true],
        "BILL_COUNTRYCODE" => ["type" => "single", "required" => true],

        //delivery
        "DELIVERY_FNAME" => ["type" => "single", "required" => true],
        "DELIVERY_LNAME" => ["type" => "single", "required" => true],
        "DELIVERY_COMPANY" => ["type" => "single"],
        "DELIVERY_EMAIL" => ["type" => "single"],
        "DELIVERY_PHONE" => ["type" => "single", "required" => true],
        "DELIVERY_ADDRESS" => ["type" => "single", "required" => true],
        "DELIVERY_ADDRESS2" => ["type" => "single"],
        "DELIVERY_ZIPCODE" => ["type" => "single", "required" => true],
        "DELIVERY_CITY" => ["type" => "single", "required" => true],
        "DELIVERY_STATE" => ["type" => "single", "required" => true],
        "DELIVERY_COUNTRYCODE" => ["type" => "single", "required" => true],
    ];

    //hash fields
    public $hashFields = [
        "MERCHANT",
        "ORDER_REF",
        "ORDER_DATE",
        "ORDER_PNAME",
        "ORDER_PCODE",
        "ORDER_PINFO",
        "ORDER_PRICE",
        "ORDER_QTY",
        "ORDER_VAT",
        "ORDER_SHIPPING",
        "PRICES_CURRENCY",
        "DISCOUNT",
        "PAY_METHOD"
    ];

    /**
     * Constructor of SimpleLiveUpdate class
     *
     * @param array  $config   Configuration array or filename
     * @param string $currency Transaction currency
     *
     * @return void
     */
    public function __construct($config = [], $currency = '')
    {
        $this->setDefaults(array($this->validFields));
        $config = $this->merchantByCurrency($config, $currency);
        $this->setup($config);
        if (isset($this->debug_liveupdate)) {
            $this->debug = $this->debug_liveupdate;
        }
        $this->setField("PRICES_CURRENCY", $currency);
        $this->setField("ORDER_DATE", @date("Y-m-d H:i:s"));
        $this->fieldData['MERCHANT'] = $this->merchantId;
        $this->debugMessage[] = 'MERCHANT: ' . $this->fieldData['MERCHANT'];
        $this->targetUrl = $this->luUrl;
    }


    /**
     * Generates a ready-to-insert HTML FORM
     *
     * @param string $formName          The ID parameter of the form
     * @param string $submitElement     The type of the submit element ('button' or 'link')
     * @param string $submitElementText The label for the submit element
     *
     * @return string HTML form
     */
    public function createHtmlForm($formName = 'SimplePayForm', $submitElement = 'button', $submitElementText = 'Start Payment')
    {
        if (count($this->errorMessage) > 0) {
            return false;
        }
        if (!$this->prepareFields("ORDER_HASH")) {
            $this->errorMessage[] = 'HASH FIELD: Missing hash field name';
            return false;
        }

        $logString = "";
        $this->luForm = "\n<form action='" . $this->baseUrl . $this->targetUrl . "' method='POST' id='" . $formName . "' accept-charset='UTF-8'>";
        foreach ($this->formData as $name => $field) {
            if (is_array($field)) {
                foreach ($field as $subField) {
                    $this->luForm .= $this->createHiddenField($name . "[]", $subField);
                    $logString .= $name . '=' . $subField . "\n";
                }
            } elseif (!is_array($field)) {
                if ($name == "BACK_REF" or $name == "TIMEOUT_URL") {
                    $concat = '?';
                    if (strpos($field, '?') !== false) {
                        $concat = '&';
                    }
                    $field .= $concat . 'order_ref=' . $this->fieldData['ORDER_REF'] . '&order_currency=' . $this->fieldData['PRICES_CURRENCY'];
                    $field = $this->protocol . '://' . $field;
                }
                $this->luForm .= $this->createHiddenField($name, $field);
                $logString .= $name . '=' . $field . "\n";
            }
        }
        $this->luForm .= $this->createHiddenField("SDK_VERSION", $this->sdkVersion);
        $this->luForm .= $this->formSubmitElement($formName, $submitElement, $submitElementText);
        $this->luForm .= "\n</form>";
        $this->logFunc("LiveUpdate", $this->formData, $this->formData['ORDER_REF']);
        $this->debugMessage[] = 'HASH CODE: ' . $this->hashCode;
        return $this->luForm;
    }


    /**
     * Generates HTML submit element
     *
     * @param string $formName          The ID parameter of the form
     * @param string $submitElement     The type of the submit element ('button' or 'link')
     * @param string $submitElementText The lebel for the submit element
     *
     * @return string HTML submit
     */
    protected function formSubmitElement($formName = '', $submitElement = 'button', $submitElementText = '')
    {
        switch ($submitElement) {
        case 'link':
            $element = "\n<a href='javascript:document.getElementById(\"" . $formName ."\").submit()'>" . addslashes($submitElementText) . "</a>";
            break;
        case 'button':
            $element = "\n<button type='submit'>" . addslashes($submitElementText) . "</button>";
            break;
        case 'auto':
            $element = "\n<button type='submit'>" . addslashes($submitElementText) . "</button>";
            $element .= "\n<script language=\"javascript\" type=\"text/javascript\">document.getElementById(\"" . $formName . "\").submit();</script>";
            break;
        default :
            $element = "\n<button type='submit'>" . addslashes($submitElementText) . "</button>";
            break;
        }
        return $element;
    }
}
