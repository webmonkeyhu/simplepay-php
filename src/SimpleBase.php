<?php

declare(strict_types=1);

namespace Webmonkey\SimplePay;

use function array_keys;
use function array_key_exists;
use function is_array;
use function str_replace;
use function count;
use function strpos;
use function hash_hmac;
use function StripSlashes;
use function strlen;
use function date;
use function time;
use function is_writable;
use function file_exists;
use function is_object;
use function file_put_contents;
use function iconv;
use function mb_detect_encoding;
use function mb_detect_order;
use function print_r;
use function highlight_string;

/**
 * Base class for SimplePay implementation
 *
 * @category SDK
 * @package  SimplePay_SDK
 * @author   SimplePay IT <itsupport@otpmobil.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.html  GNU GENERAL PUBLIC LICENSE (GPL V3.0)
 * @link     http://simplepartner.hu/online_fizetesi_szolgaltatas.html
 */
class SimpleBase
{
    protected $merchantId;
    protected $secretKey;
    protected $hashCode;
    protected $hashString;
    protected $hashData = [];
    protected $runMode = 'LIVE';
    public $sdkVersion = 'SimplePay_PHP_SDK_1.0.7_171207';
    public $debug = false;
    public $logger = true;
    public $logPath = "log";
    public $hashFields = [];
    public $debugMessage = [];
    public $errorMessage = [];
    public $deniedInputChars = ["'", "\\", "\""];
    public $defaultsData = [
        'BASE_URL' => "https://secure.simplepay.hu/payment/", //LIVE system
        'SANDBOX_URL' => "https://sandbox.simplepay.hu/payment/", //SANDBOX system
        'LU_URL' => "order/lu.php",   //relative to BASE_URL
        'ALU_URL' => "order/alu.php", //relative to BASE_URL
        'IDN_URL' => "order/idn.php", //relative to BASE_URL
        'IRN_URL' => "order/irn.php", //relative to BASE_URL
        'IOS_URL' => "order/ios.php", //relative to BASE_URL
        'OC_URL' => "order/tokens/"   //relative to BASE_URL
    ];

    public $settings = [
        'MERCHANT' => 'merchantId',
        'SECRET_KEY' => 'secretKey',
        'BASE_URL' => 'baseUrl',
        'ALU_URL' => 'aluUrl',
        'LU_URL' => 'luUrl',
        'IOS_URL' => 'iosUrl',
        'IDN_URL' => 'idnUrl',
        'IRN_URL' => 'irnUrl',
        'OC_URL' => 'ocUrl',
        'GET_DATA' => 'getData',
        'POST_DATA' => 'postData',
        'SERVER_DATA' => 'serverData',
        'PROTOCOL' => 'protocol',
        'SANDBOX' => 'sandbox',
        'CURL' => 'curl',
        'LOGGER' => 'logger',
        'LOG_PATH' => 'logPath',
        'DEBUG_LIVEUPDATE_PAGE' => 'debug_liveupdate_page',
        'DEBUG_LIVEUPDATE' => 'debug_liveupdate',
        'DEBUG_BACKREF' => 'debug_backref',
        'DEBUG_IPN' => 'debug_ipn',
        'DEBUG_IRN' => 'debug_irn',
        'DEBUG_IDN' => 'debug_idn',
        'DEBUG_IOS' => 'debug_ios',
        'DEBUG_ONECLICK' => 'debug_oneclick',
        'DEBUG_ALU' => 'debug_alu',
    ];

    /**
     * Initialize MERCHANT, SECRET_KEY and CURRENCY
     *
     * @param string $config   Config array
     * @param string $currency Currency
     *
     * @return array $this->config Initialized config array
     */
    public function merchantByCurrency($config = [], $currency = '')
    {
        if (!is_array($config)) {
            $this->errorMessage[] = 'config is not array!';
            return false;
        } elseif (count($config) == 0) {
            $this->errorMessage[] = 'Empty config array!';
            return false;
        }

        $config['CURRENCY'] = str_replace(' ', '', $currency);
        $variables = ['MERCHANT', 'SECRET_KEY'];

        foreach ($variables as $var) {
            if (isset($config[$currency . '_' . $var])) {
                $config[$var] = str_replace(' ', '', $config[$currency . '_' . $var]);
            } elseif (!isset($config[$currency . '_' . $var])) {
                $config[$var] = 'MISSING_' . $var;
                $this->errorMessage[] = 'Missing ' . $var;
            }
        }

        if ($this->debug) {
            foreach ($config as $configKey => $configValue) {
                if (strpos($configKey, 'SECRET_KEY') !== true) {
                    $this->debugMessage[] = $configKey . '=' . $configValue;
                }
            }
        }
        return $config;
    }

    /**
     * Initial settings
     *
     * @param array $config Array with config options
     *
     * @return boolean
     */
    public function setup($config = [])
    {
        if (isset($config['SANDBOX'])) {
            if ($config['SANDBOX']) {
                $this->defaultsData['BASE_URL'] = $this->defaultsData['SANDBOX_URL'];
                $this->runMode = 'SANDBOX';
            }
        }
        $this->processConfig($this->defaultsData);
        $this->processConfig($config);

        if ($this->commMethod == 'liveupdate' && isset($config['BACK_REF'])) {
            $this->setField("BACK_REF", $config['BACK_REF']);
        }
        if ($this->commMethod == 'liveupdate' && isset($config['TIMEOUT_URL'])) {
            $this->setField("TIMEOUT_URL", $config['TIMEOUT_URL']);
        }
        return true;
    }

    /**
     * Set config options
     *
     * @param array $config Array with config options
     *
     * @return void
     */
    public function processConfig($config = [])
    {
        foreach (array_keys($config) as $setting) {
            if (array_key_exists($setting, $this->settings)) {
                $prop = $this->settings[$setting];
                $this->$prop = $config[$setting];
            }
        }
    }

    /**
     * HMAC HASH creation
     *
     * @param string $key  Secret key for encryption
     * @param string $data String to encode
     *
     * @return string HMAC hash
     */
    protected function hmac($key = '', $data = '')
    {
        if ($data == '') {
            $this->errorMessage[] = 'DATA FOR HMAC: MISSING!';
            return false;
        }
        if ($key == '') {
            $this->errorMessage[] = 'KEY FOR HMAC: MISSING!';
            return false;
        }
        return hash_hmac('md5', $data, trim($key));
    }

    /**
     * Create HASH code for an array (1-dimension only)
     *
     * @param array $hashData Array of ordered fields to be HASH-ed
     *
     * @return string Hash code
     */
    protected function createHashString($hashData = [])
    {
        if (count($hashData) == 0) {
            $this->errorMessage[] = 'HASH_DATA: hashData is empty, so we can not generate hash string ';
            return false;
        }

        $hashString = '';
        $cunter = 1;
        foreach ($hashData as $field) {
            if (is_array($field)) {
                $this->errorMessage[] = 'HASH_ARRAY: No multi-dimension array allowed!';
                return false;
            }
            $hashString .= strlen(StripSlashes($field)).$field;
            if ($this->commMethod != 'alu') {
                $this->debugMessage[] = 'HASH_VALUE_' . $cunter .'('.strlen($field).'): '. $field;
            }
            $cunter++;
        }

        $this->hashString = $hashString;
        if ($this->commMethod != 'alu') {
            $this->debugMessage[] = 'HASH string: ' . $this->hashString;
        }
        $this->hashCode = $this->hmac($this->secretKey, $this->hashString);
        return $this->hashCode;
    }

    /**
     * Creates a 1-dimension array from a 2-dimension one
     *
     * @param array $array Array to be processed
     * @param array $skip  Array of keys to be skipped when creating the new array
     *
     * @return array $return Flat array
     */
    public function flatArray($array = [], $skip = [])
    {
        if (count($array) == 0) {
            $this->errorMessage[] = 'FLAT_ARRAY: array for flatArray is empty';
            return [];
        }
        $return = [];
        foreach ($array as $name => $item) {
            if (!in_array($name, $skip)) {
                if (is_array($item)) {
                    foreach ($item as $subItem) {
                        $return[] = $subItem;
                    }
                } elseif (!is_array($item)) {
                    $return[] = $item;
                }
            }
        }
        return $return;
    }

    /**
     * Write log
     *
     * @param string $state   State of the payment process
     * @param array  $data    Data of the log
     * @param string $orderId External ID of order
     *
     * @return void
     */
    public function logFunc($state = '', $data = [], $orderId = 0)
    {

        if ($this->logger) {
            $date = @date('Y-m-d H:i:s', time());
            $logFile = $this->logPath . '/' . @date('Ymd', time()) . '.log';

            if (!is_writable($this->logPath)) {
                $msg = 'LOG: log folder (' . $this->logPath . ') is not writable ';
                if (!in_array($msg, $this->debugMessage)) {
                    $this->debugMessage[] = $msg;
                }
                return false;
            }
            if (file_exists($logFile)) {
                if (!is_writable($logFile)) {
                    $msg = 'LOG: log file (' . $logFile . ') is not writable ';
                    if (!in_array($msg, $this->debugMessage)) {
                        $this->debugMessage[] = $msg;
                    }
                    return false;
                }
            }

            $logtext = $orderId . ' ' . $state . ' ' . $date . ' RUN_MODE=' . $this->runMode . "\n";
            foreach ($data as $logkey => $logvalue) {
                if (is_object($logvalue)) {
                    $logvalue = (array) $logvalue;
                }
                if (is_array($logvalue)) {
                    foreach ($logvalue as $subvalue) {
                        if (is_object($subvalue)) {
                            $subvalue = (array) $subvalue;
                        }
                        if (is_array($subvalue)) {
                            foreach ($subvalue as $subvalue2Key => $subvalue2Value) {
                                $logtext .= $orderId . ' ' . $state . ' ' . $date . ' ' . $subvalue2Key . '=' . $subvalue2Value . "\n";
                            }
                        }
                        else {
                            $logtext .= $orderId . ' ' . $state . ' ' . $date . ' ' . $logkey . '=' . $subvalue . "\n";
                        }
                    }
                } elseif (!is_array($logvalue)) {
                    $logtext .= $orderId . ' ' . $state . ' ' . $date . ' ' . $logkey . '=' . $logvalue . "\n";
                }
            }
            file_put_contents($logFile, $logtext, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Error logger
     *
     * @return void
     */
    public function errorLogger()
    {
        switch ($this->commMethod) {
        case 'liveupdate':
            $orderId = $this->formData['ORDER_REF'];
            $type = "LiveUpdate";
            break;
        case 'backref':
            $orderId = $this->order_ref;
            $type = "BackRef";
            break;
        case 'ios':
            $orderId = $this->orderNumber;
            $type = "IOS";
            break;
        case 'ipn':
            $orderId = @$this->postData['REFNOEXT'];
            $type = "IPN";
            break;
        case 'idn':
            $orderId = $this->refnoext;
            $type = "IDN";
            break;
        case 'irn':
            $orderId = $this->refnoext;
            $type = "IRN";
            break;
        case 'timeout':
            $orderId = $this->order_ref;
            $type = "TIMEOUT";
            break;
        case 'oneclick':
            $orderId = $this->formData['EXTERNAL_REF'];
            $type = "OneClick";
            break;
        case 'alu':
            $orderId = $this->formData['ORDER_REF'];
            $type = "ALU";
            break;

        default:
            $orderId = 'EMPTY';
            $type = 'general';
            $this->debugMessage[] = 'DEBUG_LOGGER_UNDEFINED_ID: ' . $orderId;
            $this->debugMessage[] = 'DEBUG_LOGGER_UNDEFINED_TYPE: ' . $type;
            break;
        }

        $errorCounter = count($this->errorMessage);

        $log = [];
        if ($this->debug || $errorCounter > 0) {
            $counter = 1;
            foreach ($this->debugMessage as $item) {
                $log['ITEM_' . $counter] = $item;
                $counter++;
            }
            $this->logFunc($type . '_DEBUG', $log, $orderId);
        }

        $log = [];
        if ($errorCounter > 0) {
            $counter = 1;
            foreach ($this->errorMessage as $item) {
                $log['ITEM_' . $counter] = $item;
                $counter++;
            }
            $this->logFunc($type . '_ERROR', $log, $orderId);
        }
    }

    /**
     * Returns string without extra characters
     *
     * @param string $string String for clean
     *
     * @return string $string
     */
    public function cleanString($string = '')
    {
        return str_replace($this->deniedInputChars, '', $string);
    }

    /**
     * Prints all of error message
     *
     * @return void
     */
    public function getErrorMessage()
    {
        $message = $this->getDebugMessage();
        $message .= '<font color="red">ERROR START</font><br>';
        foreach ($this->errorMessage as $items) {
            $message .= "-----------------------------------------------------------------------------------<br>";
            if (is_array($items) || is_object($items)) {
                $message .= "<pre>";
                $message .= $items;
                $message .= "</pre>";
            } elseif (!is_array($items) && !is_object($items)) {
                $message .= $items . '<br/>';
            }
            $message .= "-----------------------------------------------------------------------------------<br>";
        }
        $message .= '<font color="red">ERROR END</font><br>';
        iconv(mb_detect_encoding($message, mb_detect_order(), true), "UTF-8", $message);
        return $message;
    }

    /**
     * Prints all of debug elements
     *
     * @return void
     */
    public function getDebugMessage()
    {
        $message = '<font color="red">DEBUG START</font><br>';
        foreach ($this->debugMessage as $items) {
            if (is_array($items) || is_object($items)) {
                 $message .= "<pre>";
                 $message .= print_r($items, true) . '<br/>';
                 $message .= "</pre>";
            } elseif (!is_array($items) && !is_object($items)) {
                if (strpos($items, 'form action=') !== false) {
                    $message .= highlight_string($items, true) . '<br/>';
                } else {
                    $message .= $items . '<br/>';
                }
            }
        }

        if ($this->commMethod == 'liveupdate') {
            $message .= "-----------------------------------------------------------------------------------<br>";
            $message .= 'HASH FIELDS ' . print_r($this->hashFields, true);

            $message .= "-----------------------------------------------------------------------------------<br>";
            $message .= 'HASH DATA ' . print_r($this->hashData, true);

            $message .= "-----------------------------------------------------------------------------------<br>";
            $message .= highlight_string(@$this->luForm, true) . '<br/>';

            $message .= "-----------------------------------------------------------------------------------<br>";
            $message .= 'HASH CHECK ' . "<a href='http://hash.online-convert.com/md5-generator'>ONLINE HASH CONVERTER</a><br>";
        }
        $message .= "-----------------------------------------------------------------------------------<br>";
        $message .= '<font color="red">DEBUG END</font><br>';
        iconv(mb_detect_encoding($message, mb_detect_order(), true), "UTF-8", $message);
        return $message;
    }

}
