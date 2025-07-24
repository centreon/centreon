<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Tests\Core\Common\Domain\ValueObject\Web;

use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Domain\ValueObject\Identity\Email;
use Core\Common\Domain\ValueObject\Web\IpAddress;

it('with valid IPv4', function () {
    $string = "170.0.0.1";
    $ipAddress = new IpAddress($string);
    expect($ipAddress->getValue())->toBe($string);
});

it('with valid IPv6', function () {
    $string = "2001:0db8:85a3:0000:0000:8a2e:0370:7334";
    $ipAddress = new IpAddress($string);
    expect($ipAddress->getValue())->toBe($string);
});

it('with invalid IPv6', function () {
    $string = "2001:0db8:85a3:0000:0000:8a2e:0370";
    $ipAddress = new IpAddress($string);
})->throws(ValueObjectException::class);

it('with an incorrect ip address', function () {
    $string = "yoyo";
    $ipAddress = new IpAddress($string);
})->throws(ValueObjectException::class);

it('is not empty', function () {
    $string = "170.0.0.1";
    $ipAddress = new IpAddress($string);
    expect($ipAddress->isEmpty())->toBeFalse();
});

it('is empty', function () {
    $string = "";
    $ipAddress = new IpAddress($string);
})->throws(ValueObjectException::class);

it('starts with', function () {
    $ipAddress = new IpAddress("170.0.0.1");
    expect($ipAddress->startsWith("170"))->toBeTrue();
});

it('not starts with', function () {
    $ipAddress = new IpAddress("170.0.0.1");
    expect($ipAddress->startsWith("200"))->toBeFalse();
});

it('ends with', function () {
    $ipAddress = new IpAddress("170.0.0.1");
    expect($ipAddress->endsWith(".1"))->toBeTrue();
});

it('not ends with', function () {
    $ipAddress = new IpAddress("170.0.0.1");
    expect($ipAddress->endsWith(".180"))->toBeFalse();
});

it('replace', function () {
    $ipAddress = new IpAddress("170.0.0.1");
    $newIpAddress = $ipAddress->replace("170", "200");
    expect($ipAddress)
        ->toBeInstanceOf(IpAddress::class)
        ->and($ipAddress->getValue())
        ->toBe("170.0.0.1")
        ->and($newIpAddress)
        ->toBeInstanceOf(IpAddress::class)
        ->and($newIpAddress->getValue())
        ->toBe("200.0.0.1");
});

it('contains', function () {
    $ipAddress = new IpAddress("170.0.0.1");
    expect($ipAddress->contains("0.1"))->toBeTrue();
});

it('not contains', function () {
    $ipAddress = new IpAddress("170.0.0.1");
    expect($ipAddress->contains("0.3"))->toBeFalse();
});

it('append', function () {
    $ipAddress = new IpAddress("170.0.0.1");
    $newIpAddress = $ipAddress->append("82");
    expect($ipAddress)
        ->toBeInstanceOf(IpAddress::class)
        ->and($ipAddress->getValue())
        ->toBe("170.0.0.1")
        ->and($newIpAddress)
        ->toBeInstanceOf(IpAddress::class)
        ->and($newIpAddress->getValue())
        ->toBe("170.0.0.182");
});

it('length', function () {
    $ipAddress = new IpAddress("170.0.0.1");
    expect($ipAddress->length())->toBe(9);
});

it('equal', function () {
    $IpAddress1 = new IpAddress("170.0.0.1");
    $IpAddress2 = new IpAddress("170.0.0.1");
    expect($IpAddress1->equals($IpAddress2))->toBeTrue();
});

it('not equal', function () {
    $IpAddress1 = new IpAddress("170.0.0.1");
    $IpAddress2 = new IpAddress("170.0.0.2");
    expect($IpAddress1->equals($IpAddress2))->toBeFalse();
});

it('equal with incorrect type', function () {
    $IpAddress1 = new IpAddress("170.0.0.1");
    $IpAddress2 = new \DateTime();
    $IpAddress1->equals($IpAddress2);
})->throws(\TypeError::class);

it('equal with incorrect value object type', function () {
    $IpAddress1 = new IpAddress("170.0.0.1");
    $IpAddress2 = new Email("yoyo@toto.fr");
    $IpAddress1->equals($IpAddress2);
})->throws(ValueObjectException::class);

it('magic method toString', function () {
    $ipAddress = new IpAddress("170.0.0.1");
    expect("$ipAddress")->toBe("170.0.0.1");
});

it('json serialize', function () {
    $ipAddress = new IpAddress("170.0.0.1");
    expect($ipAddress->jsonSerialize())->toBe('170.0.0.1');
});
