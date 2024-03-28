<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Security\Token\Application\UseCase\AddTokenValidation;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Security\Token\Application\Exception\TokenException;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use Core\Security\Token\Application\UseCase\AddToken\AddTokenValidation;

beforeEach(function (): void {
    $this->validation = new AddTokenValidation(
        $this->readTokenRepository = $this->createMock(ReadTokenRepositoryInterface::class),
        $this->readContactRepository = $this->createMock(ReadContactRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class)
    );
});

it('throws an exception when name is already used', function (): void {
    $this->readTokenRepository
        ->expects($this->once())
        ->method('existsByNameAndUserId')
        ->willReturn(true);

    $this->validation->assertIsValidName('  name test ', 1);
})->throws(
    TokenException::class,
    TokenException::nameAlreadyExists(trim('name test'))->getMessage()
);

it('throws an exception when user is not allowed to manage other\'s token', function (): void {
    $this->user
        ->expects($this->once())
        ->method('getId')
        ->willReturn(2);
    $this->user
        ->expects($this->once())
        ->method('hasRole')
        ->willReturn(false);

    $this->validation->assertIsValidUser(1);
})->throws(
    TokenException::class,
    TokenException::notAllowedToCreateTokenForUser(1)->getMessage()
);

it('throws an exception when user ID does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('getId')
        ->willReturn(2);
    $this->user
        ->expects($this->once())
        ->method('hasRole')
        ->willReturn(true);
    $this->readContactRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validation->assertIsValidUser(1);
})->throws(
    TokenException::class,
    TokenException::invalidUserId(1)->getMessage()
);
