<?php

declare(strict_types=1);

namespace Webmonkey\SimplePay\Tests\Assets;

use JsonSerializable;

class TestJsonSerializable implements JsonSerializable {
    public function jsonSerialize() {
        return [];
    }
}
