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

beforeEach(function (): void {
        $this->createUser = static fn(array $fields = []): User => new User(
            ...[
                'id' => 1,
                'name' => 'user-name',
                'alias' => 'user-alias',
                'email' => 'user@email.com',
                'isAdmin' => false,
                'theme' => User::THEME_LIGHT,
                'userInterfaceDensity' => User::USER_INTERFACE_DENSITY_EXTENDED,
                'canReachFrontend' => true,
                ...$fields,
            ]
        );
});

// too short fields
foreach (
    [
        'name' => User::MIN_NAME_LENGTH,
        'alias' => User::MIN_ALIAS_LENGTH,
        'email' => User::MIN_EMAIL_LENGTH,
        'theme' => User::MIN_THEME_LENGTH,
    ] as $field => $length
) {
    $tooShort = '';
    it(
        "should throw an exception when user {$field} is too short",
        fn() => ($this->createUser)([$field => $tooShort])
    )->throws(
        InvalidArgumentException::class,
        AssertionException::minLength($tooShort, 0, $length, "User::{$field}")->getMessage()
    );
}

// too long fields
foreach (
    [
        'name' => User::MAX_NAME_LENGTH,
        'alias' => User::MAX_ALIAS_LENGTH,
        'email' => User::MAX_EMAIL_LENGTH,
        'theme' => User::MAX_THEME_LENGTH,
    ] as $field => $length
) {
    $tooLong = str_repeat('a', $length + 1);
    it(
        "should throw an exception when user {$field} is too long",
        fn() => ($this->createUser)([$field => $tooLong])
    )->throws(
        InvalidArgumentException::class,
        AssertionException::maxLength($tooLong, $length + 1, $length, "User::{$field}")->getMessage()
    );
}

// invalid fields
it(
    'should throw an exception when user ID is invalid',
    fn() => ($this->createUser)(['id' => 0])
)->throws(
    InvalidArgumentException::class,
    AssertionException::positiveInt(0, 'User::id')->getMessage()
);

it(
    'should throw an exception when user interface density is invalid',
    fn() => ($this->createUser)(['userInterfaceDensity' => 'hello world'])
)->throws(
    \InvalidArgumentException::class,
    'User interface view mode provided not handled'
);
