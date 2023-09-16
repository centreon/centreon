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

declare(strict_types = 1);

namespace Tests\Core\Security\Token\Application\UseCase\FindTokens;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Common\Domain\TrimmedString;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\Token\Application\Exception\TokenException;
use Core\Security\Token\Application\UseCase\FindTokens\FindTokens;
use Core\Security\Token\Application\UseCase\FindTokens\FindTokensResponse;
use Core\Security\Token\Application\UseCase\FindTokens\TokenDto;
use Core\Security\Token\Domain\Model\Token;
use Core\Security\Token\Infrastructure\Repository\DbReadTokenRepository;
use Tests\Core\Security\Token\Infrastructure\API\FindTokens\FindTokensPresenterStub;

beforeEach(closure: function (): void {
    $this->repository = $this->createMock(DbReadTokenRepository::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->useCase = new FindTokens(
        $this->createMock(RequestParametersInterface::class),
        $this->repository,
        $this->user
    );
    $this->presenter = new FindTokensPresenterStub($this->createMock(PresenterFormatterInterface::class));
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->method('hasTopologyRole')
        ->with(Contact::ROLE_ADMINISTRATION_API_TOKENS_RW)
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('hasRole')
        ->with(Contact::ROLE_MANAGE_TOKENS)
        ->willReturn(true);
    $this->repository
        ->expects($this->once())
        ->method('findByRequestParameters')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(TokenException::errorWhileSearching(new \Exception())->getMessage());
});

it('should present a ForbiddenResponse when the user does not have read rights', function (): void {
    $this->user
        ->method('hasTopologyRole')
        ->with(Contact::ROLE_ADMINISTRATION_API_TOKENS_RW)
        ->willReturn(false);

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class);
});

it('should present a FindTokensResponse when a non-admin user has read rights', function (): void {
    $this->user
        ->method('hasTopologyRole')
        ->with(Contact::ROLE_ADMINISTRATION_API_TOKENS_RW)
        ->willReturn(true);

    $this->user
        ->method('hasRole')
        ->with(Contact::ROLE_MANAGE_TOKENS)
        ->willReturn(false);

    $creationDate = new \DateTime();
    $expirationDate = $creationDate->add(\DateInterval::createFromDateString('1 day'));
    $this->repository
        ->expects($this->once())
        ->method('findByIdAndRequestParameters')
        ->willReturn([
            new Token(
                new TrimmedString('fake'),
                1,
                new TrimmedString('non-admin'),
                1,
                new TrimmedString('non-admin'),
                $creationDate,
                $expirationDate,
                false
            ),
        ]);

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(FindTokensResponse::class)
        ->and($this->presenter->response->tokens)
        ->toHaveCount(1)
        ->and(serialize($this->presenter->response->tokens[0]))
        ->toBe(
            serialize(
                new TokenDto(
                    'fake',
                    1,
                    'non-admin',
                    1,
                    'non-admin',
                    $creationDate,
                    $expirationDate,
                    false
                )
            )
        );
});

it('should present a FindTokensResponse when user is an admin', function (): void {
    $this->user
        ->method('hasTopologyRole')
        ->with(Contact::ROLE_ADMINISTRATION_API_TOKENS_RW)
        ->willReturn(true);

    $this->user
        ->method('hasRole')
        ->with(Contact::ROLE_MANAGE_TOKENS)
        ->willReturn(true);

    $creationDate = new \DateTime();
    $expirationDate = $creationDate->add(\DateInterval::createFromDateString('1 day'));
    $this->repository
        ->expects($this->once())
        ->method('findByRequestParameters')
        ->willReturn([
            new Token(
                new TrimmedString('fake'),
                1,
                new TrimmedString('non-admin'),
                1,
                new TrimmedString('non-admin'),
                $creationDate,
                $expirationDate,
                false
            ),
        ]);

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(FindTokensResponse::class)
        ->and($this->presenter->response->tokens)
        ->toHaveCount(1)
        ->and(serialize($this->presenter->response->tokens[0]))
        ->toBe(
            serialize(
                new TokenDto(
                    'fake',
                    1,
                    'non-admin',
                    1,
                    'non-admin',
                    $creationDate,
                    $expirationDate,
                    false
                )
            )
        );
});
