<?php

declare(strict_types=1);

namespace Webmonkey\SimplePay\Traits;

use Exception;

use function curl_close;
use function curl_errno;
use function curl_error;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;

use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HEADER;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_URL;
use const CURLOPT_USERAGENT;

trait CommunicationTrait
{
    /**
     * Handler for cURL communication
     *
     * @param string $url     URL
     * @param string $data    Sending data to URL
     * @param array  $headers Header information for POST
     * @return array Result of cURL communication
     */
    public function runCommunication(string $url = '', string $data = '', array $headers = [])
    {
        $result   = '';
        $curlData = curl_init();

        curl_setopt($curlData, CURLOPT_URL, $url);
        curl_setopt($curlData, CURLOPT_POST, true);
        curl_setopt($curlData, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curlData, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlData, CURLOPT_USERAGENT, 'curl');
        curl_setopt($curlData, CURLOPT_TIMEOUT, 60);
        curl_setopt($curlData, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curlData, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curlData, CURLOPT_HEADER, true);
        // cURL + SSL
        // curl_setopt($curlData, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($curlData, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($curlData);

        $this->result   = $result;
        $this->curlInfo = curl_getinfo($curlData);

        try {
            if (curl_errno($curlData)) {
                throw new Exception(curl_error($curlData));
            }
        } catch (Exception $e) {
            $this->logContent['runCommunicationException'] = $e->getMessage();
        }

        curl_close($curlData);

        return $result;
    }
}
