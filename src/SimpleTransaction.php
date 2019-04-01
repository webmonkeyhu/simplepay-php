<?php

declare(strict_types=1);

namespace Webmonkey\SimplePay;

use Webmonkey\SimplePay\SimpleBase;

use function array_key_exists;
use function array_pop;
use function date;
use function time;
use function in_array;
use function get_loaded_extensions;
use function http_build_query;
use function stream_context_create;
use function file_get_contents;
use function count;
use function curl_init;
use function curl_setopt;
use function curl_exec;
use function curl_getinfo;
use function curl_close;
use function substr;
use function preg_match_all;
use function explode;

/**
 * Class for SimplePay transaction handling
 *
 * @category SDK
 * @package  SimplePay_SDK
 * @author   SimplePay IT <itsupport@otpmobil.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.html  GNU GENERAL PUBLIC LICENSE (GPL V3.0)
 * @link     http://simplepartner.hu/online_fizetesi_szolgaltatas.html
 */
class SimpleTransaction extends SimpleBase
{
    public $result;
    public $targetUrl;
    public $baseUrl;
    public $curlInfo;
    public $formData = [];
    public $fieldData = [];
    public $missing = [];
    protected $products = [];
    protected $productFields = ['name', 'code', 'info', 'price', 'qty', 'vat'];

    /**
     * Sends a HTTP request via cURL or file_get_contents() and returns the response
     *
     * @param string $url    Base URL for request
     * @param array  $data   Parameters to send
     * @param string $method Request method
     *
     * @return array $result Response
     */
    public function startRequest($url = '', $data = [], $method = 'POST')
    {
        $this->debugMessage[] = 'SEND START TIME' . ': ' . @date("Y-m-d H:i:s", time());
        $this->debugMessage[] = 'SEND METHOD' . ': ' . $method;
        $this->debugMessage[] = 'SEND URL' . ': ' . $url;
        foreach ($data as $dataKey => $dataValue) {
            if ($this->commMethod != 'alu') {
                $this->debugMessage[] = 'SEND DATA ' . $dataKey . ': ' . $dataValue;
            }
        }
        if (!$this->curl) {
            //XML content
            $this->debugMessage[] = 'SEND WAY: file_get_contents';
            if (in_array("libxml", get_loaded_extensions())) {
                $options = [
                    'http' => [
                      'method' => $method,
                      'header' =>
                          "Accept-language: en\r\n".
                          "Content-type: application/x-www-form-urlencoded\r\n",
                      'content' => http_build_query($data, '', '&')
                ]];

                $context = stream_context_create($options);
                $result = @file_get_contents($url, true, $context);
                if (!$result) {
                    $this->errorMessage[] = 'file_get_contents() error.';
                    $this->errorMessage[] = 'Maybe your server (' . $this->serverData['SERVER_NAME'] . ') can not reach SimplePay service on file_get_contents() way.';
                }
                $this->debugMessage[] = 'SEND END TIME' . ': ' . @date("Y-m-d H:i:s", time());
                return $result;
            } elseif (!in_array("libxml", get_loaded_extensions())) {
                $this->errorMessage[] = 'libxml extension is missing or not activated.';
            }
        } elseif ($this->curl) {
            //cURL
            $this->debugMessage[] = 'SEND WAY: cURL';
            if (in_array("curl",  get_loaded_extensions())) {
                $curlData = curl_init();
                curl_setopt($curlData, CURLOPT_URL, $url);
                curl_setopt($curlData, CURLOPT_POST, true);
                if ($method != "POST") {
                    curl_setopt($curlData, CURLOPT_POST, false);
                }
                curl_setopt($curlData, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($curlData, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curlData, CURLOPT_USERAGENT, 'curl');
                curl_setopt($curlData, CURLOPT_TIMEOUT, 60);
                curl_setopt($curlData, CURLOPT_FOLLOWLOCATION, true);
                //cURL + SSL
                //curl_setopt($curlData, CURLOPT_SSL_VERIFYPEER, false);
                //curl_setopt($curlData, CURLOPT_SSL_VERIFYHOST, false);
                $result = curl_exec($curlData);
                if (!$result) {
                    $this->errorMessage[] = 'cURL result error.';
                    $this->errorMessage[] = 'Maybe your server (' . $this->serverData['SERVER_NAME'] . ') can not reach SimplePay service on cURL() way.';
                }

                $this->curlInfo = curl_getinfo($curlData);
                foreach ($this->curlInfo as $curlKey => $curlValue) {
                    if (!is_array($curlValue)) {
                        $value = $curlValue;
                    } elseif (is_array($curlValue)) {
                        if (count($curlValue) == 0) {
                            $value = '';
                        } elseif (count($curlValue) > 0) {
                            foreach ($curlValue as $cvKey => $cvValue) {
                                $this->debugMessage[] = 'cURL_INFO ' . $curlKey . ' ' . $cvKey . ': ' . $cvValue;
                            }
                        }
                    }
                    $this->debugMessage[] = 'cURL_INFO ' . $curlKey . ': ' . $value;
                    if ($curlKey == 'http_code') {
                        if ($curlValue != 200) {
                            $this->errorMessage[] = 'cURL HTTP CODE is: ' . $curlValue;
                            $this->errorMessage[] = 'cURL URL: ' . $this->curlInfo['url'];
                        }
                    }
                }
                curl_close($curlData);
                $this->debugMessage[] = 'SEND END TIME' . ': ' . @date("Y-m-d H:i:s", time());
                return $result;
            } elseif (!in_array("curl",  get_loaded_extensions())) {
                $this->errorMessage[] = 'cURL extension is missing or not activated.';
            }
        }
        $this->errorMessage[] = 'SEND METHOD' . ': UNKNOWN';
        return false;
    }

    /**
     * Creates hidden HTML field
     *
     * @param string $name  Name of the field. ID parameter will be generated without "[]"
     * @param string $value Value of the field
     *
     * @return string HTML form element
     */
    public function createHiddenField($name = '', $value = '')
    {
        if ($name == '') {
            $this->errorMessage[] = 'HTML HIDDEN: field name is empty';
            return false;
        }
        $inputId = $name;
        if (substr($name, -2, 2) == "[]") {
            $inputId = substr($name, 0, -2);
        }
        return "\n<input type='hidden' name='" . $name . "' id='" . $inputId . "' value='" . $value . "' />";
    }

    /**
     * Generates raw data array with HMAC HASH code for custom processing
     *
     * @param string $hashFieldName Index-name of the generated HASH field in the associative array
     *
     * @return array Data content of form
     */
    public function createPostArray($hashFieldName = "ORDER_HASH")
    {
        if (!$this->prepareFields($hashFieldName)) {
            $this->errorMessage[] = 'POST ARRAY: Missing hash field name';
            return false;
        }
        return $this->formData;
    }

    /**
     * Sets default value for a field
     *
     * @param array $sets Array of fields and its parameters
     *
     * @return void
     */
    protected function setDefaults($sets = [])
    {
        foreach ($sets as $set) {
            foreach ($set as $field => $fieldParams) {
                if ($fieldParams['type'] == 'single' && isset($fieldParams['default'])) {
                    $this->fieldData[$field] = $fieldParams['default'];
                }
            }
        }
    }

    /**
     * Checks if all required fields are set.
     * Returns true or the array of missing fields list
     *
     * @return boolean
     */
    protected function checkRequired()
    {
        $missing = [];
        foreach ($this->validFields as $field => $params) {
            if (isset($params['required']) && $params['required']) {
                if ($params['type'] == "single") {
                    if (!isset($this->formData[$field])) {
                        $missing[] = $field;
                        $this->errorMessage[] = 'Missing field: ' . $field;
                    }
                } elseif ($params['type'] == "product") {
                    foreach ($this->products as $prod) {
                        $paramName = $params['paramName'];
                        if (!isset($prod[$paramName])) {
                            $missing[] = $field;
                            $this->errorMessage[] = 'Missing field: ' . $field;
                        }
                    }
                }
            }
        }
        $this->missing = $missing;
        return true;
    }

    /**
     * Getter method for fields
     *
     * @param string $fieldName Name of the field
     *
     * @return array Data of field
     */
    public function getField($fieldName = '')
    {
        if (isset($this->fieldData[$fieldName])) {
            return $this->fieldData[$fieldName];
        }
        $this->debugMessage[] = 'GET FIELD: Missing field name in getField: ' . $fieldName;
        return false;
    }

    /**
     * Setter method for fields
     *
     * @param string $fieldName  Name of the field to be set
     * @param imxed  $fieldValue Value of the field to be set
     *
     * @return boolean
     */
    public function setField($fieldName = '', $fieldValue = '')
    {
        if (in_array($fieldName, array_keys($this->validFields))) {
            $this->fieldData[$fieldName] = $this->cleanString($fieldValue);
            if ($fieldName == 'LU_ENABLE_TOKEN') {
                if ($fieldValue) {
                    $this->fieldData['LU_TOKEN_TYPE'] = 'PAY_BY_CLICK';
                }
            }
            return true;
        }
        $this->debugMessage[] = 'SET FIELD: Invalid field in setField: ' . $fieldName;
        return false;
    }

    /**
     * Adds product to the $this->product array
     *
     * @param mixed $product Array description of product or Product object
     *
     * @return void
     */
    public function addProduct($product = [])
    {
        if (!is_array($product)) {
            $this->errorMessage[] = 'PRODUCT: Not a valid product!';
        }
        foreach ($this->productFields as $field) {
            if (array_key_exists($field, $product)) {
                 $add[$field] = $this->cleanString($product[$field]);
            } elseif (!array_key_exists($field, $product)) {
                $add[$field] = ' ';
                $this->debugMessage[] = 'Missing product field: ' . $field;
            }
        }
        $this->products[] = $add;
    }


    /**
     * Finalizes and prepares fields for sending
     *
     * @param string $hashName Name of the field containing HMAC HASH code
     *
     * @return boolean
     */
    protected function prepareFields($hashName = '')
    {
        if (!is_string($hashName)) {
            $this->errorMessage[] = 'PREPARE: Hash name is not string!';
            return false;
        }
        $this->setHashData();
        $this->setFormData();
        if ($this->hashData) {
            $this->formData[$hashName] = $this->createHashString($this->hashData);
        }
        $this->checkRequired();
        if (count($this->missing) == 0) {
            return true;
        }
        $this->debugMessage[] = 'PREPARE: Missing required fields';
        $this->errorMessage[] = 'PREPARE: Missing required fields';
        return false;
    }

    /**
     * Set hash data by hashFields
     *
     * @return void
     */
    protected function setHashData()
    {
        foreach ($this->hashFields as $field) {
            $params = $this->validFields[$field];
            if ($params['type'] == "single") {
                if (isset($this->fieldData[$field])) {
                    $this->hashData[] = $this->fieldData[$field];
                }
            } elseif ($params['type'] == "product") {
                foreach ($this->products as $product) {
                    if (isset($product[$params["paramName"]])) {
                        $this->hashData[] = $product[$params["paramName"]];
                    }
                }
            }
        }
    }

    /**
     * Set form data by validFields
     *
     * @return void
     */
    protected function setFormData()
    {
        foreach ($this->validFields as $field => $params) {
            if (isset($params["rename"])) {
                $field = $params["rename"];
            }
            if ($params['type'] == "single") {
                if (isset($this->fieldData[$field])) {
                    $this->formData[$field] = $this->fieldData[$field];
                }
            } elseif ($params['type'] == "product") {
                if (!isset($this->formData[$field])) {
                    $this->formData[$field] = [];
                }
                foreach ($this->products as $num => $product) {
                    if (isset($product[$params["paramName"]])) {
                        $this->formData[$field][$num] = $product[$params["paramName"]];
                    }
                }
            }
        }
    }

    /**
     * Finds and processes validation response from HTTP response
     *
     * @param string $resp HTTP response
     *
     * @return array Data
     */
    public function processResponse($resp = '')
    {
        preg_match_all("/<EPAYMENT>(.*?)<\/EPAYMENT>/", $resp, $matches);
        $data = explode("|", $matches[1][0]);
        if (is_array($data)) {
            if (count($data) > 0) {
                $counter = 1;
                foreach ($data as $dataValue) {
                    $this->debugMessage[] = 'EPAYMENT_ELEMENT_' . $counter .': ' . $dataValue;
                    $counter++;
                }
            }
        }
        return $this->nameData($data);
    }

    /**
     * Validates HASH code of the response
     *
     * @param array $resp Array with the response data
     *
     * @return boolean
     */
    public function checkResponseHash($resp = [])
    {
        $hash = 'N/A';
        if (isset($resp['ORDER_HASH'])) {
            $hash = $resp['ORDER_HASH'];
        }
        elseif (isset($resp['hash'])) {
            $hash = $resp['hash'];
        }

        array_pop($resp);
        $calculated = $this->createHashString($resp);
        $this->debugMessage[] = 'HASH ctrl: ' . $hash;
        $this->debugMessage[] = 'HASH calculated: ' . $calculated;
        if ($hash == $calculated) {
            $this->debugMessage[] = 'HASH CHECK: ' . 'Successful';
            return true;
        }
        $this->errorMessage[] = 'HASH ctrl: ' . $hash;
        $this->errorMessage[] = 'HASH calculated: ' . $calculated;
        $this->errorMessage[] = 'HASH CHECK: ' . 'Fail';
        $this->debugMessage[] = 'HASH CHECK: ' . 'Fail';
        return false;
    }
}
