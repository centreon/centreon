<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
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

namespace Tests\Centreon\Domain\Common;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Centreon\Domain\Common\Assertion\AssertionException;

$propertyPath = 'Class::property';

// ────────────────────────────── FAILURES tests ──────────────────────────────
$failDataProvider = [
    'maxLength("oooX", 3)' => [
        fn() => Assertion::maxLength('oooX', 3, $propertyPath),
        AssertionException::maxLength('oooX', 4, 3, $propertyPath)->getMessage(),
        AssertionException::INVALID_MAX_LENGTH,
    ],
    'maxLength("utf8: éà", 7)' => [
        fn() => Assertion::maxLength('utf8: éà', 7, $propertyPath),
        AssertionException::maxLength('utf8: éà', 8, 7, $propertyPath)->getMessage(),
        AssertionException::INVALID_MAX_LENGTH,
    ],
    'minLength("", 1)' => [
        fn() => Assertion::minLength('', 1, $propertyPath),
        AssertionException::minLength('', 0, 1, $propertyPath)->getMessage(),
        AssertionException::INVALID_MIN_LENGTH,
    ],
    'minLength("utf8: éà", 9)' => [
        fn() => Assertion::minLength('utf8: éà', 9, $propertyPath),
        AssertionException::minLength('utf8: éà', 8, 9, $propertyPath)->getMessage(),
        AssertionException::INVALID_MIN_LENGTH,
    ],
    'email("not-an-email")' => [
        fn() => Assertion::email('not-an-email', $propertyPath),
        AssertionException::email('not-an-email', $propertyPath)->getMessage(),
        AssertionException::INVALID_EMAIL,
    ],
    'positiveInt(0)' => [
        fn() => Assertion::positiveInt(0, $propertyPath),
        AssertionException::positiveInt(0, $propertyPath)->getMessage(),
        AssertionException::INVALID_MIN,
    ],
    'min(0, 1)' => [
        fn() => Assertion::min(0, 1, $propertyPath),
        AssertionException::min(0, 1, $propertyPath)->getMessage(),
        AssertionException::INVALID_MIN,
    ],
    'max(43, 42)' => [
        fn() => Assertion::max(43, 42, $propertyPath),
        AssertionException::max(43, 42, $propertyPath)->getMessage(),
        AssertionException::INVALID_MAX,
    ],
    'greaterOrEqualThan(41, 42)' => [
        fn() => Assertion::greaterOrEqualThan(41, 42, $propertyPath),
        AssertionException::greaterOrEqualThan(41, 42, $propertyPath)->getMessage(),
        AssertionException::INVALID_GREATER_OR_EQUAL,
    ],
    'notEmpty(NULL)' => [
        fn() => Assertion::notEmpty(null, $propertyPath),
        AssertionException::notEmpty($propertyPath)->getMessage(),
        AssertionException::VALUE_EMPTY,
    ],
    'notEmpty(false)' => [
        fn() => Assertion::notEmpty(false, $propertyPath),
        AssertionException::notEmpty($propertyPath)->getMessage(),
        AssertionException::VALUE_EMPTY,
    ],
    'notEmpty(0)' => [
        fn() => Assertion::notEmpty(0, $propertyPath),
        AssertionException::notEmpty($propertyPath)->getMessage(),
        AssertionException::VALUE_EMPTY,
    ],
    'notEmpty(0.0)' => [
        fn() => Assertion::notEmpty(0.0, $propertyPath),
        AssertionException::notEmpty($propertyPath)->getMessage(),
        AssertionException::VALUE_EMPTY,
    ],
    'notEmpty("")' => [
        fn() => Assertion::notEmpty('', $propertyPath),
        AssertionException::notEmpty($propertyPath)->getMessage(),
        AssertionException::VALUE_EMPTY,
    ],
    'notEmpty("0")' => [
        fn() => Assertion::notEmpty('0', $propertyPath),
        AssertionException::notEmpty($propertyPath)->getMessage(),
        AssertionException::VALUE_EMPTY,
    ],
    'notEmpty([])' => [
        fn() => Assertion::notEmpty([], $propertyPath),
        AssertionException::notEmpty($propertyPath)->getMessage(),
        AssertionException::VALUE_EMPTY,
    ],
    'notEmptyString(NULL)' => [
        fn() => Assertion::notEmptyString(null, $propertyPath),
        AssertionException::notEmptyString($propertyPath)->getMessage(),
        AssertionException::VALUE_EMPTY,
    ],
    'notEmptyString("")' => [
        fn() => Assertion::notEmptyString('', $propertyPath),
        AssertionException::notEmptyString($propertyPath)->getMessage(),
        AssertionException::VALUE_EMPTY,
    ],
    'notNull(NULL)' => [
        fn() => Assertion::notNull(null, $propertyPath),
        AssertionException::notNull($propertyPath)->getMessage(),
        AssertionException::VALUE_NULL,
    ],
    'inArray(2, [1, "a", NULL])' => [
        fn() => Assertion::inArray(2, [1, 'a', null], $propertyPath),
        AssertionException::inArray(2, [1, 'a', null], $propertyPath)->getMessage(),
        AssertionException::INVALID_CHOICE,
    ],
    'range(0.9, 1.0, 2)' => [
        fn() => Assertion::range(0.9, 1.0, 2, $propertyPath),
        AssertionException::range(0.9, 1.0, 2, $propertyPath)->getMessage(),
        AssertionException::INVALID_RANGE,
    ],
    'range(2.1, 1.0, 2)' => [
        fn() => Assertion::range(2.1, 1.0, 2, $propertyPath),
        AssertionException::range(2.1, 1.0, 2, $propertyPath)->getMessage(),
        AssertionException::INVALID_RANGE,
    ],
    'regex("abc123", "/^\d+$/")' => [
        fn() => Assertion::regex('abc123', '/^\d+$/', $propertyPath),
        AssertionException::matchRegex('abc123', '/^\d+$/', $propertyPath)->getMessage(),
        AssertionException::INVALID_REGEX,
    ],
    'regex(1234, "/^\d+$/")' => [
        fn() => Assertion::regex(1234, '/^\d+$/', $propertyPath),
        AssertionException::matchRegex('1234', '/^\d+$/', $propertyPath)->getMessage(),
        AssertionException::INVALID_REGEX,
    ],
    'ipOrDomain("")' => [
        fn() => Assertion::ipOrDomain('', $propertyPath),
        AssertionException::ipOrDomain('', $propertyPath)->getMessage(),
        AssertionException::INVALID_IP_OR_DOMAIN,
    ],
    'ipOrDomain("not:valid")' => [
        fn() => Assertion::ipOrDomain('not:valid', $propertyPath),
        AssertionException::ipOrDomain('not:valid', $propertyPath)->getMessage(),
        AssertionException::INVALID_IP_OR_DOMAIN,
    ],
    'ipAddress("any-hostname")' => [
        fn() => Assertion::ipAddress('any-hostname', $propertyPath),
        AssertionException::ipAddressNotValid('any-hostname', $propertyPath)->getMessage(),
        AssertionException::INVALID_IP,
    ],
    'ipAddress(1234)' => [
        fn() => Assertion::ipAddress(1234, $propertyPath),
        AssertionException::ipAddressNotValid('1234', $propertyPath)->getMessage(),
        AssertionException::INVALID_IP,
    ],
    'maxDate(new DateTime("2023-02-08 16:00:01"), new DateTime("2023-02-08 16:00:00"))' => [
        fn() => Assertion::maxDate(
            new \DateTime('2023-02-08 16:00:01'),
            new \DateTime('2023-02-08 16:00:00'),
            $propertyPath
        ),
        AssertionException::maxDate(
            new \DateTime('2023-02-08 16:00:01'),
            new \DateTime('2023-02-08 16:00:00'),
            $propertyPath
        )->getMessage(),
        AssertionException::INVALID_MAX_DATE,
    ],
    "isInstanceOf('test', Exception:class)" => [
        fn() => Assertion::isInstanceOf('test', \Exception::class, $propertyPath),
        AssertionException::badInstanceOfObject(gettype('test'), \Exception::class, $propertyPath)->getMessage(),
        AssertionException::INVALID_INSTANCE_OF,
    ],
    'jsonString("not:valid")' => [
        fn() => Assertion::jsonString('not:valid', $propertyPath),
        AssertionException::invalidJsonString($propertyPath)->getMessage(),
        AssertionException::INVALID_JSON_STRING,
    ],
    'jsonString("")' => [
        fn() => Assertion::jsonString('', $propertyPath),
        AssertionException::invalidJsonString($propertyPath)->getMessage(),
        AssertionException::INVALID_JSON_STRING,
    ],
    'arrayJsonEncodable("malformed utf8 : ..")' => [
        fn() => Assertion::jsonEncodable("malformed utf8 :\xd8\x3d", $propertyPath),
        AssertionException::notJsonEncodable($propertyPath)->getMessage(),
        AssertionException::INVALID_ARRAY_JSON_ENCODABLE,
    ],
    'arrayJsonEncodable("too long")' => [
        fn() => Assertion::jsonEncodable('too long', $propertyPath, 5),
        AssertionException::maxLength('<JSON>', 10, 5, $propertyPath)->getMessage(),
        AssertionException::INVALID_MAX_LENGTH,
    ],
    "unauthorisedCharacters('new bad string!', '!&#@')" => [
        fn() => Assertion::unauthorizedCharacters('new bad string!', '!&#@', $propertyPath),
        AssertionException::unauthorizedCharacters('new bad string!', '!', $propertyPath)->getMessage(),
        AssertionException::INVALID_CHARACTERS
    ],
];

// We use a custom data provider with a loop to avoid pest from auto-evaluating the closure from the dataset.
foreach ($failDataProvider as $name => [$failAssertion, $failMessage, $failCode]) {
    it(
        'should throw an exception for ' . $name . ' (' . random_int(1,10000). ')',
        function () use ($propertyPath, $failAssertion, $failMessage, $failCode): void {
            try {
                $failAssertion();
                $this->fail('This SHALL NOT pass.');
            } catch (AssertionFailedException $e) {
                // expected class + message + propertyPath + code
                expect($e->getMessage())->toBe($failMessage)
                    ->and($e->getPropertyPath())->toBe($propertyPath)
                    ->and($e->getCode())->toBe($failCode);
            } catch(\Throwable) {
                $this->fail('This SHALL NOT pass.');
            }
        }
    );
}

// ────────────────────────────── SUCCESS tests ──────────────────────────────
$successDataProvider = [
    'maxLength("ooo", 3)' => [
        fn() => Assertion::maxLength('ooo', 3, $propertyPath),
    ],
    'maxLength("utf8: éà", 8)' => [
        fn() => Assertion::maxLength('utf8: éà', 8, $propertyPath),
    ],
    'minLength("o", 1)' => [
        fn() => Assertion::minLength('o', 1, $propertyPath),
    ],
    'minLength("utf8: éà", 8)' => [
        fn() => Assertion::minLength('utf8: éà', 8, $propertyPath),
    ],
    'email("ok@centreon.com")' => [
        fn() => Assertion::email('ok@centreon.com', $propertyPath),
    ],
    'positiveInt(1)' => [
        fn() => Assertion::positiveInt(1, $propertyPath),
    ],
    'min(1, 1)' => [
        fn() => Assertion::min(1, 1, $propertyPath),
    ],
    'max(42, 42)' => [
        fn() => Assertion::max(42, 42, $propertyPath),
    ],
    'greaterOrEqualThan(42, 42)' => [
        fn() => Assertion::greaterOrEqualThan(42, 42, $propertyPath),
    ],
    'notEmpty(true)' => [
        fn() => Assertion::notEmpty(true, $propertyPath),
    ],
    'notEmpty(1)' => [
        fn() => Assertion::notEmpty(1, $propertyPath),
    ],
    'notEmpty(1.5)' => [
        fn() => Assertion::notEmpty(1, $propertyPath),
    ],
    'notEmpty("abc")' => [
        fn() => Assertion::notEmpty('abc', $propertyPath),
    ],
    'notEmpty([42])' => [
        fn() => Assertion::notEmpty([42], $propertyPath),
    ],
    'notEmptyString("0")' => [
        fn() => Assertion::notEmptyString('0', $propertyPath),
    ],
    'notEmptyString("abc")' => [
        fn() => Assertion::notEmptyString('abc', $propertyPath),
    ],
    'notNull(0)' => [
        fn() => Assertion::notNull(0, $propertyPath),
    ],
    'notNull(0.0)' => [
        fn() => Assertion::notNull(0.0, $propertyPath),
    ],
    'notNull("")' => [
        fn() => Assertion::notNull('', $propertyPath),
    ],
    'notNull([])' => [
        fn() => Assertion::notNull([], $propertyPath),
    ],
    'inArray(NULL, [1, "a", NULL])' => [
        fn() => Assertion::inArray(null, [1, 'a', null], $propertyPath),
    ],
    'range(1.1, 1.0, 2)' => [
        fn() => Assertion::range(1.1, 1.0, 2, $propertyPath),
    ],
    'range(1, 1.0, 2)' => [
        fn() => Assertion::range(1, 1.0, 2, $propertyPath),
    ],
    'range(2, 1.0, 2)' => [
        fn() => Assertion::range(2, 1.0, 2, $propertyPath),
    ],
    'range(2.0, 1.0, 2)' => [
        fn() => Assertion::range(2.0, 1.0, 2, $propertyPath),
    ],
    'regex("1234", "/^\d+$/")' => [
        fn() => Assertion::regex('1234', '/^\d+$/', $propertyPath),
    ],
    'ipOrDomain("1.2.3.4")' => [
        fn() => Assertion::ipOrDomain('1.2.3.4', $propertyPath),
    ],
    'ipOrDomain("any-hostname")' => [
        fn() => Assertion::ipOrDomain('any-hostname', $propertyPath),
    ],
    'ipAddress("2.3.4.5")' => [
        fn() => Assertion::ipAddress('2.3.4.5', $propertyPath),
    ],
    'maxDate(new DateTime("2023-02-08 16:00:00"), new DateTime("2023-02-08 16:00:00"))' => [
        fn() => Assertion::maxDate(
            new \DateTime('2023-02-08 16:00:00'),
            new \DateTime('2023-02-08 16:00:00'),
            $propertyPath
        ),
    ],
    "isInstanceOf(new \Exception(), \Throwable::class)" => [
        fn() => Assertion::isInstanceOf(new \Exception(), \Throwable::class, $propertyPath),
    ],
    "jsonString('{}')" => [
        fn() => Assertion::jsonString('{}', $propertyPath),
    ],
    "jsonString('[]')" => [
        fn() => Assertion::jsonString('[]', $propertyPath),
    ],
    "jsonString('42')" => [
        fn() => Assertion::jsonString('42', $propertyPath),
    ],
    "jsonString('42.42')" => [
        fn() => Assertion::jsonString('42', $propertyPath),
    ],
    "jsonString('null')" => [
        fn() => Assertion::jsonString('null', $propertyPath),
    ],
    "jsonString('false')" => [
        fn() => Assertion::jsonString('false', $propertyPath),
    ],
    "jsonString('true')" => [
        fn() => Assertion::jsonString('true', $propertyPath),
    ],
    "jsonString('\"foo\"')" => [
        fn() => Assertion::jsonString('"foo"', $propertyPath),
    ],
    'arrayJsonEncodable([1,2,3])' => [
        fn() => Assertion::jsonEncodable([1, 2, 3], $propertyPath),
    ],
    'arrayJsonEncodable("foo")' => [
        fn() => Assertion::jsonEncodable('foo', $propertyPath),
    ],
    "unauthorisedCharacters('new bad string!', '&#@')" => [
        fn() => Assertion::unauthorizedCharacters('new bad string!', '&#@', $propertyPath),
    ],
    "unauthorisedCharacters('new bad string!&#@', '')" => [
        fn() => Assertion::unauthorizedCharacters('new bad string!&#@', '', $propertyPath),
    ]
];

// We use a custom data provider with a loop to avoid pest from auto-evaluating the closure from the dataset.
foreach ($successDataProvider as $name => [$successAssertion]) {
    it(
        'should not throw an exception for ' . $name . ' (' . random_int(1,10000). ')',
        function () use ($successAssertion): void {
            try {
                $successAssertion();
                expect(true)->toBeTrue();
            } catch (\Throwable) {
                $this->fail('This SHALL NOT fail.');
            }
        }
    );
}
