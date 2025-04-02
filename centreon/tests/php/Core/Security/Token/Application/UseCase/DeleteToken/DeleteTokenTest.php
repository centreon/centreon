<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
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
use Core\Security\Token\Application\UseCase\DeleteToken\DeleteToken;
use Core\Security\Token\Domain\Model\Token;
use Core\Security\Token\Domain\Model\TokenTypeEnum;

beforeEach(function (): void {
    $this->presenter = new DefaultPresenter(
        $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class)
    );

    $this->useCase = new DeleteToken(
        $this->writeTokenRepository = $this->createMock(WriteTokenRepositoryInterface::class),
        $this->readTokenRepository = $this->createMock(ReadTokenRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
    );

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
        isRevoked: false,
        type: TokenTypeEnum::API
    );
});

it('should present an ErrorResponse when a generic exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readTokenRepository
        ->expects($this->once())
        ->method('findByNameAndUserId')
        ->willReturn($this->token);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->writeTokenRepository
        ->expects($this->once())
        ->method('deleteByNameAndUserId')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->presenter, $this->token->getName(), $this->linkedUser['id']);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(TokenException::deleteToken()->getMessage());
});

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->presenter, $this->token->getName());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(TokenException::deleteNotAllowed()->getMessage());
});

it('should present a NotFoundResponse when the token does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readTokenRepository
        ->expects($this->once())
        ->method('findByNameAndUserId')
        ->willReturn(null);

    ($this->useCase)($this->presenter, $this->token->getName());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe('Token not found');
});

it('should present a NoContentResponse on success', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

   $this->readTokenRepository
       ->expects($this->once())
       ->method('findByNameAndUserId')
       ->willReturn($this->token);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->writeTokenRepository
        ->expects($this->once())
        ->method('deleteByNameAndUserId');

    ($this->useCase)($this->presenter, $this->token->getName());

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});
