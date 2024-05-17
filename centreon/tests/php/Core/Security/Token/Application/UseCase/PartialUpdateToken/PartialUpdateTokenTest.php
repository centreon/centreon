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

namespace Tests\Core\Security\Token\Application\UseCase\PartialUpdate;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Domain\TrimmedString;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\Token\Application\Exception\TokenException;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use Core\Security\Token\Application\Repository\WriteTokenRepositoryInterface;
use Core\Security\Token\Application\UseCase\PartialUpdateToken\PartialUpdateToken;
use Core\Security\Token\Application\UseCase\PartialUpdateToken\PartialUpdateTokenRequest;
use Core\Security\Token\Domain\Model\Token;

beforeEach(function (): void {
    $this->presenter = new DefaultPresenter(
        $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class)
    );
    $this->useCase = new PartialUpdateToken(
        $this->contact = $this->createMock(ContactInterface::class),
        $this->readTokenRepository = $this->createMock(ReadTokenRepositoryInterface::class),
        $this->writeTokenRepository = $this->createMock(WriteTokenRepositoryInterface::class),
        $this->dataStorageEngin = $this->createMock(DataStorageEngineInterface::class)
    );
    $this->request = new PartialUpdateTokenRequest();
    $this->linkedUser = ['id' => 23, 'name' => 'Jane Doe'];
    $this->creator = ['id' => 12, 'name' => 'John Doe'];

    $this->creationDate = new \DateTimeImmutable();
    $this->expirationDate = $this->creationDate->add(new \DateInterval('P1Y'));

    $this->token = new Token(
        name: new TrimmedString('my-token-name'),
        userId: $this->linkedUser['id'],
        userName: new TrimmedString($this->linkedUser['name']),
        creatorId: $this->creator['id'],
        creatorName: new TrimmedString($this->creator['name']),
        creationDate: $this->creationDate,
        expirationDate: $this->expirationDate,
        isRevoked: false
    );
});

it('should present an Error Response when a generic exception is thrown', function (): void {
    $this->contact
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readTokenRepository
        ->expects($this->once())
        ->method('findByNameAndUserId')
        ->willReturn($this->token);

    $this->writeTokenRepository
        ->expects($this->once())
        ->method('update')
        ->willThrowException(new \Exception());

    $this->request->isRevoked = false;

    ($this->useCase)($this->request, $this->presenter, $this->token->getName(), $this->linkedUser['id']);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(TokenException::errorWhilePartiallyUpdatingToken()->getMessage());
});

it('should present a Forbidden Response when the user does not have sufficient rights', function (): void {
    $this->contact
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter, $this->token->getName(), $this->linkedUser['id']);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(TokenException::notAllowedToPartiallyUpdateToken()->getMessage());
});

it('should present a Not Found Response when no token exists for a given name and/or user ID', function (): void {
    $this->contact
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readTokenRepository
        ->expects($this->once())
        ->method('findByNameAndUserId')
        ->willReturn(null);

    ($this->useCase)($this->request, $this->presenter, $this->token->getName(), $this->linkedUser['id']);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe('Token not found');
});

it('should present a No Content Response when token is successfully revoked', function (): void {
    $this->contact
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readTokenRepository
        ->expects($this->once())
        ->method('findByNameAndUserId')
        ->willReturn($this->token);

    ($this->useCase)($this->request, $this->presenter, $this->token->getName(), $this->linkedUser['id']);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NoContentResponse::class);
});
