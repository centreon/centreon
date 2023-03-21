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

namespace Centreon\Domain\Common\Assertion;

/**
 * This class is designed to allow the translation of error messages and provide them with a unique format.
 */
class Assertion
{
    /**
     * Assert that string value is not longer than $maxLength characters.
     *
     * @param string $value Value to test
     * @param int $maxLength Maximum length of the expected value in characters
     * @param string|null $propertyPath Property's path (ex: Host::name)
     *
     * @throws \Assert\AssertionFailedException
     */
    public static function maxLength(string $value, int $maxLength, ?string $propertyPath = null): void
    {
        $length = \mb_strlen($value, 'utf8');
        if ($length > $maxLength) {
            throw AssertionException::maxLength($value, $length, $maxLength, $propertyPath);
        }
    }

    /**
     * Assert that a string is at least $minLength characters long.
     *
     * @param string $value Value to test
     * @param int $minLength Minimum length of the expected value in characters
     * @param string|null $propertyPath Property's path (ex: Host::name)
     *
     * @throws \Assert\AssertionFailedException
     */
    public static function minLength(string $value, int $minLength, ?string $propertyPath = null): void
    {
        $length = \mb_strlen($value, 'utf8');
        if ($length < $minLength) {
            throw AssertionException::minLength($value, $length, $minLength, $propertyPath);
        }
    }

    /**
     * Assert that a value is at least an integer > 0.
     *
     * @param int $value Value to test
     * @param string|null $propertyPath Property's path (ex: Host::maxCheckAttempts)
     *
     * @throws \Assert\AssertionFailedException
     */
    public static function positiveInt(int $value, ?string $propertyPath = null): void
    {
        self::min($value, 1, $propertyPath);
    }

    /**
     * Assert that a value is at least as big as a given limit.
     *
     * Same as {@see self::greaterOrEqualThan()} but with a different message.
     *
     * @param int $value Value to test
     * @param int $minValue Minimum value
     * @param string|null $propertyPath Property's path (ex: Host::maxCheckAttempts)
     *
     * @throws \Assert\AssertionFailedException
     */
    public static function min(int $value, int $minValue, ?string $propertyPath = null): void
    {
        if ($value < $minValue) {
            throw AssertionException::min($value, $minValue, $propertyPath);
        }
    }

    /**
     * Assert that a number is smaller as a given limit.
     *
     * @param int $value Value to test
     * @param int $maxValue Maximum value
     * @param string|null $propertyPath Property's path (ex: Host::maxCheckAttempts)
     *
     * @throws \Assert\AssertionFailedException
     */
    public static function max(int $value, int $maxValue, ?string $propertyPath = null): void
    {
        if ($value > $maxValue) {
            throw AssertionException::max($value, $maxValue, $propertyPath);
        }
    }

    /**
     * Assert that a string respects email format.
     *
     * @param string $value Value to test
     * @param string|null $propertyPath Property's path (ex: User::email)
     *
     * @throws \Assert\AssertionFailedException
     */
    public static function email(string $value, ?string $propertyPath = null): void
    {
        if (! \filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw AssertionException::email($value, $propertyPath);
        }
    }

    /**
     * Assert that a date is smaller as a given limit.
     *
     * @param \DateTimeInterface $value
     * @param \DateTimeInterface $maxDate
     * @param string|null $propertyPath
     *
     * @throws \Assert\AssertionFailedException
     */
    public static function maxDate(
        \DateTimeInterface $value,
        \DateTimeInterface $maxDate,
        ?string $propertyPath = null
    ): void {
        if ($value->getTimestamp() > $maxDate->getTimestamp()) {
            throw AssertionException::maxDate($value, $maxDate, $propertyPath);
        }
    }

    /**
     * Determines if the value is greater or equal than given limit.
     *
     * Same as {@see self::min()} but with a different message.
     *
     * @param int $value Value to test
     * @param int $limit Limit value (>=)
     * @param string|null $propertyPath Property's path (ex: Host::maxCheckAttempts)
     *
     * @throws \Assert\AssertionFailedException
     */
    public static function greaterOrEqualThan(int $value, int $limit, ?string $propertyPath = null): void
    {
        if ($value < $limit) {
            throw AssertionException::greaterOrEqualThan($value, $limit, $propertyPath);
        }
    }

    /**
     * Assert that value is not empty.
     *
     * This is bound to the native php {@see https://www.php.net/manual/en/function.empty.php} function.
     *
     * Use it carefully for strings as '0' is considered empty by PHP !
     *
     * @param mixed $value Value to test
     * @param string|null $propertyPath Property's path (ex: Host::name)
     *
     * @throws \Assert\AssertionFailedException
     */
    public static function notEmpty(mixed $value, ?string $propertyPath = null): void
    {
        if (empty($value)) {
            throw AssertionException::notEmpty($propertyPath);
        }
    }

    /**
     * Assert that a string is not NULL or an empty string ''.
     *
     * This method is specifically done for strings to manage the php case `empty('0')` which is `TRUE`.
     *
     * @param ?string $value Value to test
     * @param string|null $propertyPath Property's path (ex: Host::name)
     *
     * @throws \Assert\AssertionFailedException
     */
    public static function notEmptyString(?string $value, ?string $propertyPath = null): void
    {
        if ($value === null || $value === '') {
            throw AssertionException::notEmptyString($propertyPath);
        }
    }

    /**
     * Assert that value is not null.
     *
     * @param mixed $value Value to test
     * @param string|null $propertyPath Property's path (ex: Host::name)
     *
     * @throws \Assert\AssertionFailedException
     */
    public static function notNull(mixed $value, ?string $propertyPath = null): void
    {
        if (null === $value) {
            throw AssertionException::notNull($propertyPath);
        }
    }

    /**
     * Assert that value is in array.
     *
     * @param mixed $value
     * @param mixed[] $choices
     * @param string|null $propertyPath
     *
     * @throws \Assert\AssertionFailedException
     */
    public static function inArray(mixed $value, array $choices, ?string $propertyPath = null): void
    {
        if (! \in_array($value, $choices, true)) {
            throw AssertionException::inArray($value, $choices, $propertyPath);
        }
    }

    /**
     * Assert that the value is in range.
     *
     * @param int|float $value
     * @param int|float $minValue
     * @param int|float $maxValue
     * @param string|null $propertyPath
     *
     * @throws \Assert\AssertionFailedException
     */
    public static function range(
        int|float $value,
        int|float $minValue,
        int|float $maxValue,
        ?string $propertyPath = null
    ): void {
        if ($value < $minValue || $value > $maxValue) {
            throw AssertionException::range($value, $minValue, $maxValue, $propertyPath);
        }
    }

    /**
     * Assert that a value match a regex.
     *
     * @param mixed $value
     * @param string $pattern
     * @param string|null $propertyPath
     *
     * @throws \Assert\AssertionFailedException
     */
    public static function regex(mixed $value, string $pattern, ?string $propertyPath = null): void
    {
        if (! \is_string($value) || ! \preg_match($pattern, $value)) {
            throw AssertionException::matchRegex(self::stringify($value), $pattern, $propertyPath);
        }
    }

    /**
     * Assert that value is a valid IP or Domain name.
     *
     * @param string $value
     * @param string|null $propertyPath
     *
     * @throws \Assert\AssertionFailedException
     */
    public static function ipOrDomain(string $value, ?string $propertyPath = null): void
    {
        if (
            false === filter_var($value, FILTER_VALIDATE_IP)
            && false === filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
        ) {
            throw AssertionException::ipOrDomain($value, $propertyPath);
        }
    }

    /**
     * Assert that value is a valid IP.
     *
     * @param mixed $value
     * @param string|null $propertyPath
     *
     * @throws \Assert\AssertionFailedException
     */
    public static function ipAddress(mixed $value, ?string $propertyPath = null): void
    {
        if (! \is_string($value) || false === filter_var($value, FILTER_VALIDATE_IP)) {
            throw AssertionException::ipAddressNotValid(self::stringify($value), $propertyPath);
        }
    }

    /**
     * Make a string version of a value.
     *
     * Copied from {@see \Assert\Assertion::stringify()}.
     *
     * @param mixed $value
     */
    private static function stringify(mixed $value): string
    {
        $result = \gettype($value);

        if (\is_bool($value)) {
            $result = $value ? '<TRUE>' : '<FALSE>';
        } elseif (\is_scalar($value)) {
            $val = (string) $value;

            if (\mb_strlen($val) > 100) {
                $val = \mb_substr($val, 0, 97) . '...';
            }

            $result = $val;
        } elseif (\is_array($value)) {
            $result = '<ARRAY>';
        } elseif (\is_object($value)) {
            $result = \get_debug_type($value);
        } elseif (\is_resource($value)) {
            $result = \get_resource_type($value);
        } elseif (null === $value) {
            $result = '<NULL>';
        }

        return $result;
    }
}
