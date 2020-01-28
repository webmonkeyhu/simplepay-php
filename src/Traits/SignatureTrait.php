<?php

declare(strict_types=1);

namespace Webmonkey\SimplePay\Traits;

use function base64_encode;
use function hash_equals;
use function hash_hmac;
use function strtolower;
use function trim;

trait SignatureTrait
{
    /**
     * Get full JSON hash string form hash calculation base
     *
     * @param string $data Data array for checking
     * @return string|int|null Valid JSON
     */
    public function getHashBase(string $data = '')
    {
        return $this->checkOrSetToJson($data);
    }

    /**
     * Gives HMAC signature based on key and hash string data
     *
     * @param  string $key  Secret key
     * @param  string $data Hash string
     * @return string Signature
     */
    public function getSignature(string $key = '', string $data = ''): string
    {
        if ($key === '' || $data === '') {
            $this->logContent['signatureGeneration'] = 'Empty key or data for signature';
        }

        return base64_encode(hash_hmac($this->hashAlgo, $data, trim($key), true));
    }

    /**
     * Check data based on signature
     *
     * @param string $data             Data for check
     * @param string $signatureToCheck Signature to check
     */
    public function isCheckSignature(string $data = '', string $signatureToCheck = ''): bool
    {
        $this->config['computedSignature'] = $this->getSignature($this->config['merchantKey'], $data);

        $this->logContent['signatureToCheck']  = $signatureToCheck;
        $this->logContent['computedSignature'] = $this->config['computedSignature'];

        try {
            if ($this->phpVersion === 7) {
                if (! hash_equals($this->config['computedSignature'], $signatureToCheck)) {
                    throw new Exception('fail');
                }
            } elseif ($this->phpVersion === 5) {
                if ($this->config['computedSignature'] !== $signatureToCheck) {
                    throw new Exception('fail');
                }
            }
        } catch (Exception $e) {
            $this->logContent['hashCheckResult'] = $e->getMessage();

            return false;
        }

        $this->logContent['hashCheckResult'] = 'success';

        return true;
    }

    /**
     * Get signature value from header
     *
     * @param  array $header Header
     */
    protected function getSignatureFromHeader(array $header = []): string
    {
        $signature = 'MISSING_HEADER_SIGNATURE';

        foreach ($header as $headerKey => $headerValue) {
            if (strtolower($headerKey) === 'signature') {
                $signature = trim($headerValue);
            }
        }

        return $signature;
    }
}
