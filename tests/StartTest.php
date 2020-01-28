<?php

declare(strict_types=1);

namespace Webmonkey\SimplePay\Tests;

use PHPUnit\Framework\TestCase;
use Webmonkey\SimplePay\Start;
use Webmonkey\SimplePay\Tests\Assets\TestJsonSerializable;

use function array_merge;

class StartTest extends TestCase
{
    private $underTest;

    public function setUp()
    {
        $this->underTest = new Start();

        $this->defaultTransactionBase = [
            'salt'          => '',
            'merchant'      => '',
            'orderRef'      => '',
            'currency'      => '',
            'customerEmail' => '',
            'language'      => '',
            'sdkVersion'    => '',
            'methods'       => [],
        ];

        $this->defaultTransactionBaseItem = [
            'ref'         => '',
            'title'       => '',
            'description' => '',
            'amount'      => 0,
            'price'       => 0,
            'tax'         => 0,
        ];
    }

    public function testDefaultLogContent() {
        $this->assertEquals($this->underTest->getLogContent(), [
            'runMode' => 'START',
            'PHP'     => 7.4,
        ]);
    }

    public function testAddEmptyConfigData() {
        $this->assertEquals($this->underTest->config, []);

        $this->underTest->addConfigData();

        $this->assertEquals($this->underTest->config, [
            'EMPTY_CONFIG_KEY' => ''
        ]);
    }

    public function testAddEmptyKeyConfigData() {
        $this->assertEquals($this->underTest->config, []);

        $this->underTest->addConfigData('', 'value 1');

        $this->assertEquals($this->underTest->config, [
            'EMPTY_CONFIG_KEY' => 'value 1'
        ]);
    }

    public function testAddEmptyValueConfigData() {
        $this->assertEquals($this->underTest->config, []);

        $this->underTest->addConfigData('KEY_1', '');

        $this->assertEquals($this->underTest->config, [
            'KEY_1' => ''
        ]);
    }

    public function testValidAddConfigData() {
        $this->assertEquals($this->underTest->config, []);

        $this->underTest->addConfigData('KEY_1', 'value 1');

        $this->assertEquals($this->underTest->config, [
            'KEY_1' => 'value 1'
        ]);
    }

    public function testAddConfig() {
        $this->assertEquals($this->underTest->config, []);

        $this->underTest->addConfig([
            'KEY_1' => 'value 1',
        ]);

        $this->assertEquals($this->underTest->config, [
            'KEY_1' => 'value 1'
        ]);
    }

    public function testAddEmptyData() {
        $this->assertEquals($this->underTest->transactionBase, $this->defaultTransactionBase);

        $this->underTest->addData();

        $this->assertEquals($this->underTest->transactionBase, array_merge($this->defaultTransactionBase, [
            'EMPTY_DATA_KEY' => '',
        ]));
    }

    public function testValidAddData() {
        $this->assertEquals($this->underTest->transactionBase, $this->defaultTransactionBase);

        $this->underTest->addData('KEY_1', 'value 1');

        $this->assertEquals($this->underTest->transactionBase, array_merge($this->defaultTransactionBase, [
            'KEY_1' => 'value 1',
        ]));
    }

    public function testValidAddGroupData() {
        $this->assertEquals($this->underTest->transactionBase, $this->defaultTransactionBase);

        $this->underTest->addGroupData('methods', 'KEY_1', 'value 1');

        $this->assertEquals($this->underTest->transactionBase, array_merge($this->defaultTransactionBase, [
            'methods' => [
                'KEY_1' => 'value 1',
            ]
        ]));
    }

    public function testAddItem() {
        $this->assertEquals(isset($this->underTest->transactionBase['items']), false);

        $this->underTest->addItems([]);

        $this->assertCount(1, $this->underTest->transactionBase['items']);

        $this->assertEquals($this->underTest->transactionBase['items'][0], $this->defaultTransactionBaseItem);
    }

    public function testAddItems() {
        $this->assertEquals(isset($this->underTest->transactionBase['items']), false);

        $this->underTest->addItems([]);
        $this->underTest->addItems([
            'description' => 'D1SC3P1I0N',
            'price'       => 2000,
        ]);

        $this->assertCount(2, $this->underTest->transactionBase['items']);

        $this->assertEquals($this->underTest->transactionBase['items'][0], $this->defaultTransactionBaseItem);
        $this->assertEquals($this->underTest->transactionBase['items'][1], array_merge(
            $this->defaultTransactionBaseItem,
            [
                'description' => 'D1SC3P1I0N',
                'price'       => 2000
            ]
        ));
    }

    public function testGetReturnDataReturnArrayInstance() {
        $this->assertIsArray($this->underTest->getReturnData());
    }

    public function testGetLogContentReturnArrayInstance() {
        $this->assertIsArray($this->underTest->getLogContent());
    }

    public function testCheckOrSetToJsonWhitMixedDatas() {
        $this->assertEquals($this->underTest->checkOrSetToJson(), '[]');
        $this->assertEquals($this->underTest->checkOrSetToJson(null), null);
        $this->assertEquals($this->underTest->checkOrSetToJson([]), '[]');
        $this->assertEquals($this->underTest->checkOrSetToJson(1), 1);
        $this->assertEquals($this->underTest->checkOrSetToJson((object)[]), '[]');
        $this->assertEquals($this->underTest->checkOrSetToJson(new TestJsonSerializable()), '[]');
    }
}
