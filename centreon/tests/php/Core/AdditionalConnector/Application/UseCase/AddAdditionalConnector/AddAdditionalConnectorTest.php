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

namespace Tests\Core\AdditionalConnector\Application\UseCase\AddAdditionalConnector;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\AdditionalConnector\Application\Exception\AdditionalConnectorException;
use Core\AdditionalConnector\Application\Repository\ReadAdditionalConnectorRepositoryInterface;
use Core\AdditionalConnector\Application\Repository\WriteAdditionalConnectorRepositoryInterface;
use Core\AdditionalConnector\Application\UseCase\AddAdditionalConnector\AddAdditionalConnector;
use Core\AdditionalConnector\Application\UseCase\AddAdditionalConnector\AddAdditionalConnectorRequest;
use Core\AdditionalConnector\Application\UseCase\AddAdditionalConnector\AddAdditionalConnectorResponse;
use Core\AdditionalConnector\Application\UseCase\AddAdditionalConnector\Validation\Validator;
use Core\AdditionalConnector\Domain\Model\AdditionalConnector;
use Core\AdditionalConnector\Domain\Model\Poller;
use Core\AdditionalConnector\Domain\Model\Type;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Common\Infrastructure\FeatureFlags;

beforeEach(function (): void {
    $this->presenter = new AddAdditionalConnectorPresenterStub();
    $this->useCase = new AddAdditionalConnector(
        $this->readAdditionalConnectorRepository = $this->createMock(ReadAdditionalConnectorRepositoryInterface::class),
        $this->writeAdditionalConnectorRepository = $this->createMock(WriteAdditionalConnectorRepositoryInterface::class),
        $this->validator = $this->createMock(Validator::class),
        $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->flags = new FeatureFlags(false, ''),
        $this->writeVaultAccRepositories = new \ArrayIterator([])
    );

    $this->testedAddAdditionalConnectorRequest = new AddAdditionalConnectorRequest();
    $this->testedAddAdditionalConnectorRequest->name = 'added-additionalconnector';
    $this->testedAddAdditionalConnectorRequest->type = 'vmware_v6';
    $this->testedAddAdditionalConnectorRequest->description = 'toto';
    $this->testedAddAdditionalConnectorRequest->pollers = [1];
    $this->testedAddAdditionalConnectorRequest->parameters = [
        'port' => '4242',
        'vcenters' => [
            [
                'name' => 'my-vcenter',
                'url' => 'http://10.10.10.10/sdk',
                'username' => 'admin',
                'password' => 'my-pwd',
            ],
        ],
    ];

    $this->poller = new Poller(1, 'poller-name');

    $this->testedAdditionalConnector = (new AdditionalConnector(
        id: $this->testedAdditionalConnectorId = 1,
        name: $this->testedAdditionalConnectorName = 'additionalconnector-name',
        type: $this->testedAdditionalConnectorType = Type::VMWARE_V6,
        createdBy: $this->testedAdditionalConnectorCreatedBy = 2,
        updatedBy: $this->testedAdditionalConnectorUpdatedBy = $this->testedAdditionalConnectorCreatedBy,
        createdAt: $this->testedAdditionalConnectorCreatedAt = new \DateTimeImmutable('2023-05-09T12:00:00+00:00'),
        updatedAt: $this->testedAdditionalConnectorUpdatedAt = $this->testedAdditionalConnectorCreatedAt,
        parameters: $this->testedAdditionalConnectorGlobalRefresh = $this->testedAddAdditionalConnectorRequest->parameters,
    ))->setDescription('toto');
});

it(
    'should present a ForbiddenResponse when the user does not have the correct role',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(false);

        ($this->useCase)($this->testedAddAdditionalConnectorRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(AdditionalConnectorException::addNotAllowed()->getMessage());
    }
);

it(
    'should present an ErrorResponse when a AdditionalConnectorException is thrown',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail')
            ->willThrowException(AdditionalConnectorException::nameAlreadyExists('invalid-name'));

        ($this->useCase)($this->testedAddAdditionalConnectorRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(AdditionalConnectorException::nameAlreadyExists('invalid-name')->getMessage());
    }
);

it(
    'should present an ErrorResponse when a generic exception is thrown',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->user
            ->expects($this->any())
            ->method('getId')
            ->willReturn($this->testedAdditionalConnectorCreatedBy);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail');

        $this->writeAdditionalConnectorRepository
            ->expects($this->once())
            ->method('add')
            ->willThrowException(new \Exception());

        ($this->useCase)($this->testedAddAdditionalConnectorRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(AdditionalConnectorException::addAdditionalConnector()->getMessage());
    }
);

it(
    'should present a InvalidArgumentResponse when a field value is not valid',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->user
            ->expects($this->any())
            ->method('getId')
            ->willReturn($this->testedAdditionalConnectorCreatedBy);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail');

        $this->testedAddAdditionalConnectorRequest->name = '';
        $expectedException = AssertionException::notEmptyString('NewAdditionalConnector::name');

        ($this->useCase)($this->testedAddAdditionalConnectorRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe($expectedException->getMessage());
    }
);

it(
    'should present an ErrorResponse if the newly created additionalconnector cannot be retrieved',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->user
            ->expects($this->any())
            ->method('getId')
            ->willReturn($this->testedAdditionalConnectorCreatedBy);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail');

        $this->writeAdditionalConnectorRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($this->testedAdditionalConnectorId);

        $this->readAdditionalConnectorRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn(null);

        ($this->useCase)($this->testedAddAdditionalConnectorRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(AdditionalConnectorException::errorWhileRetrievingObject()->getMessage());
    }
);

it(
    'should present a AddAdditionalConnectorResponse when no error occurs',
    function (): void {
               $this->user
                   ->expects($this->once())
                   ->method('hasTopologyRole')
                   ->willReturn(true);

        $this->user
            ->expects($this->any())
            ->method('getId')
            ->willReturn($this->testedAdditionalConnectorCreatedBy);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail');

        $this->writeAdditionalConnectorRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($this->testedAdditionalConnectorId);

        $this->readAdditionalConnectorRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($this->testedAdditionalConnector);

        $this->readAdditionalConnectorRepository
            ->expects($this->once())
            ->method('findPollersByAccId')
            ->willReturn([$this->poller]);

        ($this->useCase)($this->testedAddAdditionalConnectorRequest, $this->presenter);

        /** @var AddAdditionalConnectorResponse $additionalconnector */
        $additionalconnector = $this->presenter->data;

        expect($additionalconnector)->toBeInstanceOf(AddAdditionalConnectorResponse::class)
            ->and($additionalconnector->id)->toBe($this->testedAdditionalConnectorId)
            ->and($additionalconnector->name)->toBe($this->testedAdditionalConnectorName)
            ->and($additionalconnector->description)->toBe($this->testedAdditionalConnector->getDescription())
            ->and($additionalconnector->createdAt->getTimestamp())->toBe($this->testedAdditionalConnectorCreatedAt->getTimestamp())
            ->and($additionalconnector->updatedAt->getTimestamp())->toBeGreaterThanOrEqual(
                $this->testedAdditionalConnectorUpdatedAt->getTimestamp()
            );
    }
);
