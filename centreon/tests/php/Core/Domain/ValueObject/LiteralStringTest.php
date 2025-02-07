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

namespace Tests\Core\Domain\Common\ValueObject;

use Core\Domain\Common\Exception\InvalidValueObjectException;
use Core\Domain\Common\ValueObject\LiteralString;
use Core\Domain\Common\ValueObject\Web\IpAddress;

it('test LiteralString value object : correct instanciation', function () {
    $string = "foo";
    $literalString = new LiteralString($string);
    expect($literalString->getValue())->toBe($string);
});

it('test LiteralString value object : create from factory', function () {
    $string = "foo";
    $literalString = LiteralString::createFromString($string);
    expect($literalString->getValue())->toBe($string);
});

it('test LiteralString value object : get value', function () {
    $string = "foo";
    $literalString = LiteralString::createFromString($string);
    expect($literalString->getValue())->toBe($string);
});

it('test LiteralString value object : is empty', function () {
    $string = "";
    $literalString = LiteralString::createFromString($string);
    expect($literalString->isEmpty())->toBeTrue();
});

it('test LiteralString value object : length', function () {
    $literalString = LiteralString::createFromString("foo");
    expect($literalString->getLength())->toBe(3);
});

it('test LiteralString value object : equal', function () {
    $literalString1 = LiteralString::createFromString("foo");
    $literalString2 = LiteralString::createFromString("foo");
    expect($literalString1->equals($literalString2))->toBeTrue();
});

it('test LiteralString value object : not equal', function () {
    $literalString1 = LiteralString::createFromString("foo");
    $literalString2 = LiteralString::createFromString("bar");
    expect($literalString1->equals($literalString2))->toBeFalse();
});

it('test LiteralString value object : equal with incorrect type', function () {
    $literalString1 = LiteralString::createFromString("foo");
    $dateTime = new \DateTime();
    $literalString1->equals($dateTime);
})->throws(\TypeError::class);

it('test LiteralString value object : magic method toString', function () {
    $literalString = LiteralString::createFromString("foo");
    expect("$literalString")->toBe("foo");
});


