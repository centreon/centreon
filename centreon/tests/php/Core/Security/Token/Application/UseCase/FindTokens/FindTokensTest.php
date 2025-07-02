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

namespace Tests\Core\Security\Token\Application\UseCase\FindTokens;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Common\Domain\TrimmedString;
use Core\Security\Token\Application\Exception\TokenException;
use Core\Security\Token\Application\UseCase\FindTokens\FindTokens;
use Core\Security\Token\Application\UseCase\FindTokens\FindTokensResponse;
use Core\Security\Token\Domain\Model\ApiToken;
use Core\Security\Token\Domain\Model\JwtToken;
use Core\Security\Token\Infrastructure\Repository\DbReadTokenRepository;

beforeEach(closure: function (): void {
    $this->useCase = new FindTokens(
        $this->repository = $this->createMock(DbReadTokenRepository::class),
        $this->user = $this->createMock(ContactInterface::class),
    );
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasRole')
        ->with(Contact::ROLE_MANAGE_TOKENS)
        ->willReturn(true);
    $this->repository
        ->expects($this->once())
        ->method('findByRequestParameters')
        ->willThrowException(new \Exception());

    $response = ($this->useCase)();

    expect($response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($response->getMessage())
        ->toBe(TokenException::errorWhileSearching(new \Exception())->getMessage());
});

it('should present a FindTokensResponse when a non-admin user has read rights', function (): void {
    $this->user
        ->method('hasRole')
        ->with(Contact::ROLE_MANAGE_TOKENS)
        ->willReturn(false);

    $creationDate = new \DateTime();
    $expirationDate = $creationDate->add(\DateInterval::createFromDateString('1 day'));
    $this->repository
        ->expects($this->once())
        ->method('findByUserIdAndRequestParameters')
        ->willReturn([
            new ApiToken(
                name: new TrimmedString('fake'),
                userId: 1,
                userName: new TrimmedString('non-admin'),
                creatorId: 1,
                creatorName: new TrimmedString('non-admin'),
                creationDate: $creationDate,
                expirationDate: $expirationDate,
                isRevoked: false
            ),
        ]);

    $response = ($this->useCase)();

    expect($response)
        ->toBeInstanceOf(FindTokensResponse::class)
        ->and($response->tokens)
        ->toHaveCount(1)
        ->and(serialize($response->tokens[0]))
        ->toBe(
            serialize(
                new ApiToken(
                    name: new TrimmedString('fake'),
                    userId: 1,
                    userName: new TrimmedString('non-admin'),
                    creatorId: 1,
                    creatorName: new TrimmedString('non-admin'),
                    creationDate: $creationDate,
                    expirationDate: $expirationDate,
                    isRevoked: false
                ),
            )
        );
});

it('should present a FindTokensResponse when user is an admin', function (): void {
    $this->user
        ->method('hasTopologyRole')
        ->with(Contact::ROLE_ADMINISTRATION_AUTHENTICATION_TOKENS_RW)
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
            new JwtToken(
                name: new TrimmedString('fake'),
                creatorId: 1,
                creatorName: new TrimmedString('non-admin'),
                creationDate: $creationDate,
                expirationDate: $expirationDate,
                isRevoked: false
            ),
        ]);

    $response = ($this->useCase)();

    expect($response)
        ->toBeInstanceOf(FindTokensResponse::class)
        ->and($response->tokens)
        ->toHaveCount(1)
        ->and(serialize($response->tokens[0]))
        ->toBe(
            serialize(
                new JwtToken(
                    name: new TrimmedString('fake'),
                    creatorId: 1,
                    creatorName: new TrimmedString('non-admin'),
                    creationDate: $creationDate,
                    expirationDate: $expirationDate,
                    isRevoked: false,
                ),
            )
        );
});
