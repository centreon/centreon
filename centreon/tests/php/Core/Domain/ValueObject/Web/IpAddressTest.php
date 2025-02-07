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

namespace Tests\Core\Domain\Common\ValueObject\Web;

use Core\Domain\Common\Exception\ValueObjectException;
use Core\Domain\Common\ValueObject\Identity\Email;
use Core\Domain\Common\ValueObject\Web\IpAddress;

it('test IpAddress value object : correct instanciation', function () {
    $string = "170.0.0.1";
    $IpAddress = new IpAddress($string);
    expect($IpAddress->getValue())->toBe($string);
});

it('test Email value object with an incorrect email', function () {
    $string = "yoyo";
    $IpAddress = new IpAddress($string);
})->throws(ValueObjectException::class);

it('test IpAddress value object : create from factory', function () {
    $string = "170.0.0.1";
    $IpAddress = IpAddress::createFromString($string);
    expect($IpAddress->getValue())->toBe($string);
});

it('test Email factory with incorrect email', function () {
    $string = "yoyo";
    $IpAddress = IpAddress::createFromString($string);
})->throws(ValueObjectException::class);

it('test IpAddress value object : get value', function () {
    $string = "170.0.0.1";
    $IpAddress = IpAddress::createFromString($string);
    expect($IpAddress->getValue())->toBe($string);
});

it('test IpAddress value object : is not empty', function () {
    $string = "170.0.0.1";
    $IpAddress = IpAddress::createFromString($string);
    expect($IpAddress->isEmpty())->toBeFalse();
});

it('test IpAddress value object : is empty', function () {
    $string = "";
    $IpAddress = IpAddress::createFromString($string);
    expect($IpAddress->isEmpty())->toBeFalse();
})->throws(ValueObjectException::class);

it('test IpAddress value object : length', function () {
    $IpAddress = IpAddress::createFromString("170.0.0.1");
    expect($IpAddress->getLength())->toBe(9);
});

it('test IpAddress value object : equal', function () {
    $IpAddress1 = IpAddress::createFromString("170.0.0.1");
    $IpAddress2 = IpAddress::createFromString("170.0.0.1");
    expect($IpAddress1->equals($IpAddress2))->toBeTrue();
});

it('test IpAddress value object : not equal', function () {
    $IpAddress1 = IpAddress::createFromString("170.0.0.1");
    $IpAddress2 = IpAddress::createFromString("170.0.0.2");
    expect($IpAddress1->equals($IpAddress2))->toBeFalse();
});

it('test IpAddress value object : equal with incorrect type', function () {
    $IpAddress1 = IpAddress::createFromString("170.0.0.1");
    $IpAddress2 = new \DateTime();
    $IpAddress1->equals($IpAddress2);
})->throws(\TypeError::class);

it('test IpAddress value object : equal with incorrect value object type', function () {
    $IpAddress1 = IpAddress::createFromString("170.0.0.1");
    $IpAddress2 = new Email("yoyo@toto.fr");
    $IpAddress1->equals($IpAddress2);
})->throws(ValueObjectException::class);

it('test IpAddress value object : magic method toString', function () {
    $IpAddress = IpAddress::createFromString("170.0.0.1");
    expect("$IpAddress")->toBe("170.0.0.1");
});
