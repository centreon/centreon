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

namespace Tests\Core\Security\Token\Application\UseCase\GetToken;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Common\Domain\TrimmedString;
use Core\Security\Token\Application\Exception\TokenException;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use Core\Security\Token\Application\UseCase\GetToken\GetToken;
use Core\Security\Token\Application\UseCase\GetToken\GetTokenResponse;
use Core\Security\Token\Domain\Model\Token;
use Core\Security\Token\Domain\Model\TokenTypeEnum;

beforeEach(function (): void {
    $this->useCase = new GetToken(
        $this->readTokenRepository = $this->createMock(ReadTokenRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
    );

    $this->linkedUser = ['id' => 23, 'name' => 'Jane Doe'];
    $this->creator = ['id' => 12, 'name' => 'John Doe'];

    $this->tokenString = 'TokenString';

    $this->token = new Token(
        name: new TrimmedString($this->tokenName = 'TokenTestName'),
        userId: $this->linkedUser['id'],
        userName: new TrimmedString($this->linkedUser['name']),
        creatorId: $this->creator['id'],
        creatorName: new TrimmedString($this->creator['name']),
        creationDate: $this->creationDate = new \DateTimeImmutable(),
        expirationDate: $this->expirationDate = $this->creationDate->add(new \DateInterval('P1Y')),
        isRevoked: false,
        type: TokenTypeEnum::CMA
    );
});

it('should present an ErrorResponse when a generic exception is thrown', function (): void {
    $this->readTokenRepository
        ->expects($this->once())
        ->method('findByNameAndUserId')
        ->willThrowException(new \Exception());

    $response = ($this->useCase)($this->tokenName, $this->linkedUser['id']);

    expect($response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($response->getMessage())
        ->toBe(TokenException::errorWhileRetrievingObject()->getMessage());
});

it('should return created object on success when user ID is provided', function (): void {
    $this->readTokenRepository
        ->expects($this->once())
        ->method('findByNameAndUserId')
        ->willReturn($this->token);

    $this->readTokenRepository
        ->expects($this->once())
        ->method('findTokenString')
        ->willReturn($this->tokenString);

    $response = ($this->useCase)($this->tokenName, $this->linkedUser['id']);

    expect($response)->toBeInstanceOf(GetTokenResponse::class)
        ->and($response->apiToken)
        ->toBe($this->token)
        ->and($response->token)
        ->toBe($this->tokenString);
});
