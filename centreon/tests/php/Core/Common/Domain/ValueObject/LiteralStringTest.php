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

namespace Tests\Core\Common\Domain\ValueObject;

use Core\Common\Domain\ValueObject\LiteralString;

it('correct instanciation', function () {
    $string = "foo";
    $literalString = new LiteralString($string);
    expect($literalString->getValue())->toBe($string);
});

it('get value', function () {
    $string = "foo";
    $literalString = new LiteralString($string);
    expect($literalString->getValue())->toBe($string);
});

it('is empty', function () {
    $string = "";
    $literalString = new LiteralString($string);
    expect($literalString->isEmpty())->toBeTrue();
});

it('length', function () {
    $literalString = new LiteralString("foo");
    expect($literalString->length())->toBe(3);
});

it('to uppercase', function () {
    $literalString = new LiteralString("foo");
    $newLiteralString = $literalString->toUpperCase();
    expect($literalString)
        ->toBeInstanceOf(LiteralString::class)
        ->and($literalString->getValue())
        ->toBe("foo")
        ->and($newLiteralString)
        ->toBeInstanceOf(LiteralString::class)
        ->and($newLiteralString->getValue())
        ->toBe("FOO");
});

it('to lowercase', function () {
    $literalString = new LiteralString("FOO");
    $newLiteralString = $literalString->toLowerCase();
    expect($literalString)
        ->toBeInstanceOf(LiteralString::class)
        ->and($literalString->getValue())
        ->toBe("FOO")
        ->and($newLiteralString)
        ->toBeInstanceOf(LiteralString::class)
        ->and($newLiteralString->getValue())
        ->toBe("foo");
});

it('trim', function () {
    $literalString = new LiteralString(" foo ");
    $newLiteralString = $literalString->trim();
    expect($literalString)
        ->toBeInstanceOf(LiteralString::class)
        ->and($literalString->getValue())
        ->toBe(" foo ")
        ->and($newLiteralString)
        ->toBeInstanceOf(LiteralString::class)
        ->and($newLiteralString->getValue())
        ->toBe("foo");
});

it('starts with', function () {
    $literalString = new LiteralString("foo");
    expect($literalString->startsWith("f"))->toBeTrue();
});

it('not starts with', function () {
    $literalString = new LiteralString("foo");
    expect($literalString->startsWith("bar"))->toBeFalse();
});

it('ends with', function () {
    $literalString = new LiteralString("foo");
    expect($literalString->endsWith("o"))->toBeTrue();
});

it('not ends with', function () {
    $literalString = new LiteralString("foo");
    expect($literalString->endsWith("bar"))->toBeFalse();
});

it('replace', function () {
    $literalString = new LiteralString("foo");
    $newLiteralString = $literalString->replace("foo", "bar");
    expect($literalString)
        ->toBeInstanceOf(LiteralString::class)
        ->and($literalString->getValue())
        ->toBe("foo")
        ->and($newLiteralString)
        ->toBeInstanceOf(LiteralString::class)
        ->and($newLiteralString->getValue())
        ->toBe("bar");
});

it('contains', function () {
    $literalString = new LiteralString("foo");
    expect($literalString->contains("o"))->toBeTrue();
});

it('not contains', function () {
    $literalString = new LiteralString("foo");
    expect($literalString->contains("bar"))->toBeFalse();
});

it('append', function () {
    $literalString = new LiteralString("foo");
    $newLiteralString = $literalString->append("bar");
    expect($literalString)
        ->toBeInstanceOf(LiteralString::class)
        ->and($literalString->getValue())
        ->toBe("foo")
        ->and($newLiteralString)
        ->toBeInstanceOf(LiteralString::class)
        ->and($newLiteralString->getValue())
        ->toBe("foobar");
});

it('equal', function () {
    $literalString1 = new LiteralString("foo");
    $literalString2 = new LiteralString("foo");
    expect($literalString1->equals($literalString2))->toBeTrue();
});

it('not equal', function () {
    $literalString1 = new LiteralString("foo");
    $literalString2 = new LiteralString("bar");
    expect($literalString1->equals($literalString2))->toBeFalse();
});

it('equal with incorrect type', function () {
    $literalString1 = new LiteralString("foo");
    $dateTime = new \DateTime();
    $literalString1->equals($dateTime);
})->throws(\TypeError::class);

it('magic method toString', function () {
    $literalString = new LiteralString("foo");
    expect("$literalString")->toBe("foo");
});

it('json serialize', function () {
    $literalString = new LiteralString("foo");
    expect($literalString->jsonSerialize())->toBe('foo');
});

