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

namespace Tests\CentreonOpenTickets\Providers\Application\UseCase\FindProviders;

use Centreon\Domain\Repository\RepositoryException;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use CentreonOpenTickets\Providers\Application\Exception\ProviderException;
use CentreonOpenTickets\Providers\Application\Repository\ReadProviderRepositoryInterface;
use CentreonOpenTickets\Providers\Application\UseCase\FindProviders;
use CentreonOpenTickets\Providers\Application\UseCase\FindProvidersResponse;
use CentreonOpenTickets\Providers\Domain\Model\Provider;
use CentreonOpenTickets\Providers\Domain\Model\ProviderType;
use Core\Application\Common\UseCase\ErrorResponse;

beforeEach(closure: function (): void {
    $this->useCase = new FindProviders(
        requestParameters: $this->requestParameters = $this->createMock(RequestParametersInterface::class),
        repository: $this->repository = $this->createMock(ReadProviderRepositoryInterface::class)
    );
});

it('should present an ErrorResponse when an exception occurs for ticket provider search', function (): void {
    $exception = new RepositoryException();
    $this->repository
        ->expects($this->once())
        ->method('findAll')
        ->willThrowException($exception);

    $response = ($this->useCase)();
    expect($response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($response->getMessage())
        ->toBe(ProviderException::errorWhileListingProviders()->getMessage());
});

it('should present a FindProvidersResponse when everything goes well', function (): void {
    $provider = new Provider(
        id: 1,
        name: 'glpi',
        type: ProviderType::GlpiRestApi,
        isActivated: true
    );

    $this->repository
        ->expects($this->once())
        ->method('findAll')
        ->willReturn([$provider]);

    $response = ($this->useCase)();
    expect($response)
        ->toBeInstanceOf(FindProvidersResponse::class)
        ->and($response->providers[0]->id)->toBe($provider->getId())
        ->and($response->providers[0]->name)->toBe($provider->getName())
        ->and($response->providers[0]->type)->toBe($provider->getType())
        ->and($response->providers[0]->isActivated)->toBe($provider->isActivated());
});
