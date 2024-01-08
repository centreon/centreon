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

namespace Tests\Core\User\Domain\Model;

use Assert\InvalidArgumentException;
use Centreon\Domain\Common\Assertion\AssertionException;
use Core\User\Domain\Model\User;

// too short fields
it(
    "should throw an exception when user name is too short",
    fn() => new User(
        1,
        'user-alias',
        '',
        'user@email.com',
        false,
        User::THEME_LIGHT,
        true,
    )
)->throws(
    InvalidArgumentException::class,
    AssertionException::minLength('', 0, USER::MIN_NAME_LENGTH, "User::name")->getMessage()
);

it(
    "should throw an exception when user alias is too short",
    fn() => new User(
        1,
        '',
        'user-name',
        'user@email.com',
        false,
        User::THEME_LIGHT,
        true,
    )
)->throws(
    InvalidArgumentException::class,
    AssertionException::minLength('', 0, USER::MIN_ALIAS_LENGTH, "User::alias")->getMessage()
);

it(
    "should throw an exception when user email is too short",
    fn() => new User(
        1,
        'user-alias',
        'user-name',
        '',
        false,
        User::THEME_LIGHT,
        true,
    )
)->throws(
    InvalidArgumentException::class,
    AssertionException::minLength('', 0, USER::MIN_EMAIL_LENGTH, "User::email")->getMessage()
);

it(
    "should throw an exception when user theme is too short",
    fn() => new User(
        1,
        'user-alias',
        'user-name',
        'user@email.com',
        false,
        '',
        true,
    )
)->throws(
    InvalidArgumentException::class,
    AssertionException::minLength('', 0, USER::MIN_THEME_LENGTH, "User::theme")->getMessage()
);

// too long fields
$length = User::MAX_NAME_LENGTH;
$tooLong = str_repeat('a', $length + 1);
it(
    "should throw an exception when user name is too long",
    fn() => new User(
        1,
        'user-alias',
        $tooLong,
        'user@email.com',
        false,
        User::THEME_LIGHT,
        true,
    )
)->throws(
    InvalidArgumentException::class,
    AssertionException::maxLength($tooLong, $length + 1, $length, "User::name")->getMessage()
);

$length = User::MAX_ALIAS_LENGTH;
$tooLong = str_repeat('a', $length + 1);
it(
    "should throw an exception when user alias is too long",
    fn() => new User(
        1,
        $tooLong,
        'user-name',
        'user@email.com',
        false,
        User::THEME_LIGHT,
        true,
    )
)->throws(
    InvalidArgumentException::class,
    AssertionException::maxLength($tooLong, $length + 1, $length, "User::alias")->getMessage()
);

$length = User::MAX_EMAIL_LENGTH;
$tooLong = str_repeat('a', $length + 1);
it(
    "should throw an exception when user email is too long",
    fn() => new User(
        1,
        'user-alias',
        'user-name',
        $tooLong,
        false,
        User::THEME_LIGHT,
        true,
    )
)->throws(
    InvalidArgumentException::class,
    AssertionException::maxLength($tooLong, $length + 1, $length, "User::email")->getMessage()
);

$length = User::MAX_THEME_LENGTH;
$tooLong = str_repeat('a', $length + 1);
it(
    "should throw an exception when user theme is too long",
    fn() => new User(
        1,
        'user-alias',
        'user-name',
        'user@email.com',
        false,
        $tooLong,
        true,
    )
)->throws(
    InvalidArgumentException::class,
    AssertionException::maxLength($tooLong, $length + 1, $length, "User::theme")->getMessage()
);



// invalid fields
it(
    'should throw an exception when user ID is invalid',
    fn() => new User(
        0,
        'user-alias',
        'user-name',
        'user@email.com',
        false,
        User::THEME_LIGHT,
        true,)
)->throws(
    InvalidArgumentException::class,
    AssertionException::min(0, 1, 'User::id')->getMessage()
);
