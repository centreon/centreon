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

namespace Tests\Core\Security\Token\Application\UseCase\AddHostTemplate;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Common\Domain\TrimmedString;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\Token\Application\Exception\TokenException;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use Core\Security\Token\Application\Repository\WriteTokenRepositoryInterface;
use Core\Security\Token\Application\UseCase\AddToken\AddToken;
use Core\Security\Token\Application\UseCase\AddToken\AddTokenRequest;
use Core\Security\Token\Application\UseCase\AddToken\AddTokenResponse;
use Core\Security\Token\Application\UseCase\AddToken\AddTokenValidation;
use Core\Security\Token\Domain\Model\Token;
use Core\Security\Token\Domain\Model\TokenTypeEnum;

beforeEach(function (): void {
    $this->useCase = new AddToken(
        $this->writeTokenRepository = $this->createMock(WriteTokenRepositoryInterface::class),
        $this->readTokenRepository = $this->createMock(ReadTokenRepositoryInterface::class),
        $this->providerFactory = $this->createMock(ProviderAuthenticationFactoryInterface::class),
        $this->validation = $this->createMock(AddTokenValidation::class),
        $this->user = $this->createMock(ContactInterface::class),
    );

    $this->localProvider = $this->createMock(ProviderAuthenticationInterface::class);
    $this->configurationProvider = $this->createMock(Configuration::class);

    $this->creationDate = new \DateTimeImmutable();
    $this->expirationDate = $this->creationDate->add(new \DateInterval('P1Y'));

    $this->linkedUser = ['id' => 23, 'name' => 'Jane Doe'];
    $this->creator = ['id' => 12, 'name' => 'John Doe'];

    $this->request = new AddTokenRequest(
        name: '  token name  ',
        type: TokenTypeEnum::CMA,
        userId: $this->linkedUser['id'],
        expirationDate: $this->expirationDate
    );

    $this->token = new Token(
        name: new TrimmedString($this->request->name),
        userId: $this->linkedUser['id'],
        userName: new TrimmedString($this->linkedUser['name']),
        creatorId: $this->creator['id'],
        creatorName: new TrimmedString($this->creator['name']),
        creationDate: $this->creationDate,
        expirationDate: $this->expirationDate,
        isRevoked: false,
        type: TokenTypeEnum::CMA
    );
});

it('should present an ErrorResponse when a generic exception is thrown', function (): void {
    $this->providerFactory
        ->expects($this->once())
        ->method('create')
        ->willThrowException(new \Exception());

    $response = ($this->useCase)($this->request);

    expect($response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($response->getMessage())
        ->toBe(TokenException::addToken()->getMessage());
});

it('should present a ConflictResponse when name is already used', function (): void {
    $this->validation
        ->expects($this->once())
        ->method('assertIsValidName')
        ->willThrowException(
            TokenException::nameAlreadyExists(trim($this->request->name))
        );

    $response = ($this->useCase)($this->request);

    expect($response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($response->getMessage())
        ->toBe(
            TokenException::nameAlreadyExists(trim($this->request->name))->getMessage()
        );
});

it('should present a ConflictResponse when user ID is not valid', function (): void {
    $this->validation
        ->expects($this->once())
        ->method('assertIsValidUser')
        ->willThrowException(
            TokenException::invalidUserId($this->request->userId)
        );

    $response = ($this->useCase)($this->request);

    expect($response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($response->getMessage())
        ->toBe(TokenException::invalidUserId($this->request->userId)->getMessage());
});

it('should present a ConflictResponse when a creator cannot manage user\'s tokens', function (): void {
    $this->validation
        ->expects($this->once())
        ->method('assertIsValidUser')
        ->willThrowException(
            TokenException::notAllowedToCreateTokenForUser($this->request->userId)
        );

    $response = ($this->useCase)($this->request);

    expect($response)
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($response->getMessage())
        ->toBe(TokenException::notAllowedToCreateTokenForUser($this->request->userId)->getMessage());
});

it('should present an InvalidArgumentResponse when a field assert failed', function (): void {
    $this->providerFactory
        ->expects($this->once())
        ->method('create')
        ->willReturn($this->localProvider);
    $this->localProvider
        ->expects($this->once())
        ->method('getConfiguration')
        ->willReturn($this->configurationProvider);
    $this->configurationProvider
        ->expects($this->once())
        ->method('getId')
        ->willReturn(1);
    $this->user
        ->expects($this->once())
        ->method('getId')
        ->willReturn($this->creator['id']);
    $this->user
        ->expects($this->once())
        ->method('getName')
        ->willReturn('');

    $response = ($this->useCase)($this->request);

    expect($response)
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($response->getMessage())
        ->toBe(AssertionException::notEmptyString('NewToken::creatorName')->getMessage());
});

it('should present an ErrorResponse if the newly created token cannot be retrieved', function (): void {
    $this->providerFactory
        ->expects($this->once())
        ->method('create')
        ->willReturn($this->localProvider);
    $this->localProvider
        ->expects($this->once())
        ->method('getConfiguration')
        ->willReturn($this->configurationProvider);
    $this->configurationProvider
        ->expects($this->once())
        ->method('getId')
        ->willReturn(1);
    $this->user
        ->expects($this->once())
        ->method('getId')
        ->willReturn($this->creator['id']);
    $this->user
        ->expects($this->once())
        ->method('getName')
        ->willReturn($this->creator['name']);

    $this->writeTokenRepository
        ->expects($this->once())
        ->method('add');

    $this->readTokenRepository
        ->expects($this->once())
        ->method('find')
        ->willReturn(null);

    $response = ($this->useCase)($this->request);

    expect($response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($response->getMessage())
        ->toBe(TokenException::errorWhileRetrievingObject()->getMessage());
});

it('should return created object on success', function (): void {
    $this->validation->expects($this->once())->method('assertIsValidName');
    $this->validation->expects($this->once())->method('assertIsValidUser');

    $this->providerFactory
        ->expects($this->once())
        ->method('create')
        ->willReturn($this->localProvider);
    $this->localProvider
        ->expects($this->once())
        ->method('getConfiguration')
        ->willReturn($this->configurationProvider);
    $this->configurationProvider
        ->expects($this->once())
        ->method('getId')
        ->willReturn(1);
    $this->user
        ->expects($this->once())
        ->method('getId')
        ->willReturn($this->creator['id']);
    $this->user
        ->expects($this->once())
        ->method('getName')
        ->willReturn($this->creator['name']);

    $this->writeTokenRepository
        ->expects($this->once())
        ->method('add');

    $this->readTokenRepository
        ->expects($this->once())
        ->method('find')
        ->willReturn($this->token);

    $response = ($this->useCase)($this->request);

    expect($response)->toBeInstanceOf(AddTokenResponse::class)
        ->and($response->apiToken->getName())
        ->toBe($this->token->getName())
        ->and($response->apiToken->getUserName())
        ->toBe($this->linkedUser['name'])
        ->and($response->apiToken->getUserId())
        ->toBe($this->linkedUser['id'])
        ->and($response->apiToken->getCreatorName())
        ->toBe($this->creator['name'])
        ->and($response->apiToken->getCreatorId())
        ->toBe($this->creator['id'])
        ->and($response->apiToken->getCreationDate())
        ->toBe($this->creationDate)
        ->and($response->apiToken->getExpirationDate())
        ->toBe($this->expirationDate)
        ->and($response->apiToken->isRevoked())
        ->toBe($this->token->isRevoked())
        ->and($response->apiToken->getType())
        ->toBe($this->token->getType());
});
