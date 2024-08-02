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

namespace Tests\Core\AdditionalConnectorConfiguration\Application\UseCase\AddAcc;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\AdditionalConnectorConfiguration\Application\Exception\AccException;
use Core\AdditionalConnectorConfiguration\Application\Factory\AccFactory;
use Core\AdditionalConnectorConfiguration\Application\Repository\ReadAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Application\Repository\WriteAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Application\UseCase\AddAcc\AddAcc;
use Core\AdditionalConnectorConfiguration\Application\UseCase\AddAcc\AddAccRequest;
use Core\AdditionalConnectorConfiguration\Application\UseCase\AddAcc\AddAccResponse;
use Core\AdditionalConnectorConfiguration\Application\UseCase\AddAcc\Validator;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\AccParametersInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\NewAcc;
use Core\AdditionalConnectorConfiguration\Domain\Model\Poller;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Common\Infrastructure\FeatureFlags;

beforeEach(function (): void {
    $this->presenter = new AddAccPresenterStub();
    $this->useCase = new AddAcc(
        readAccRepository: $this->readAccRepository = $this->createMock(ReadAccRepositoryInterface::class),
        writeAccRepository: $this->writeAccRepository = $this->createMock(WriteAccRepositoryInterface::class),
        validator: $this->validator = $this->createMock(Validator::class),
        factory: $this->factory = $this->createMock(AccFactory::class),
        dataStorageEngine: $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class),
        user: $this->user = $this->createMock(ContactInterface::class),
        flags: $this->flags = new FeatureFlags(false, ''),
        writeVaultAccRepositories: $this->writeVaultAccRepositories = new \ArrayIterator([])
    );

    $this->testedAddAccRequest = new AddAccRequest();
    $this->testedAddAccRequest->name = 'added-acc';
    $this->testedAddAccRequest->type = 'vmware_v6';
    $this->testedAddAccRequest->description = 'toto';
    $this->testedAddAccRequest->pollers = [1];
    $this->testedAddAccRequest->parameters = [
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

    $this->testedNewAcc = new NewAcc(
        name: $this->testedAccName = 'acc-name',
        type: Type::VMWARE_V6,
        createdBy: $this->testedAccCreatedBy = 2,
        description: 'some-description',
        parameters: $this->createMock(AccParametersInterface::class),
    );

    $this->testedAcc = new Acc(
        id: $this->testedAccId = 1,
        name: $this->testedAccName = 'acc-name',
        type: Type::VMWARE_V6,
        createdBy: $this->testedAccCreatedBy = 2,
        updatedBy: $this->testedAccCreatedBy,
        createdAt: $this->testedAccCreatedAt = new \DateTimeImmutable('2023-05-09T12:00:00+00:00'),
        updatedAt: $this->testedAccUpdatedAt = $this->testedAccCreatedAt,
        description: 'some-description',
        parameters: $this->createMock(AccParametersInterface::class),
    );
});

it(
    'should present a ForbiddenResponse when the user does not have the correct role',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(false);

        ($this->useCase)($this->testedAddAccRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(AccException::accessNotAllowed()->getMessage());
    }
);

it(
    'should present an ErrorResponse when an AccException is thrown',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail')
            ->willThrowException(AccException::nameAlreadyExists('invalid-name'));

        ($this->useCase)($this->testedAddAccRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(AccException::nameAlreadyExists('invalid-name')->getMessage());
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
            ->willReturn($this->testedAccCreatedBy);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail');

        $this->factory
            ->expects($this->once())
            ->method('createNewAcc')
            ->willReturn($this->testedNewAcc);

        $this->writeAccRepository
            ->expects($this->once())
            ->method('add')
            ->willThrowException(new \Exception());

        ($this->useCase)($this->testedAddAccRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(AccException::addAcc()->getMessage());
    }
);

it(
    'should present an InvalidArgumentResponse when a field value is not valid',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->user
            ->expects($this->any())
            ->method('getId')
            ->willReturn($this->testedAccCreatedBy);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail');

        $expectedException = AssertionException::notEmptyString('NewAcc::name');

        $this->factory
            ->expects($this->once())
            ->method('createNewAcc')
            ->willThrowException($expectedException);

        ($this->useCase)($this->testedAddAccRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe($expectedException->getMessage());
    }
);

it(
    'should present an ErrorResponse if the newly created ACC cannot be retrieved',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->user
            ->expects($this->any())
            ->method('getId')
            ->willReturn($this->testedAccCreatedBy);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail');

        $this->factory
            ->expects($this->once())
            ->method('createNewAcc')
            ->willReturn($this->testedNewAcc);

        $this->writeAccRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($this->testedAccId);

        $this->readAccRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn(null);

        ($this->useCase)($this->testedAddAccRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(AccException::errorWhileRetrievingObject()->getMessage());
    }
);

it(
    'should present an AddAccResponse when no error occurs',
    function (): void {
               $this->user
                   ->expects($this->once())
                   ->method('hasTopologyRole')
                   ->willReturn(true);

        $this->user
            ->expects($this->any())
            ->method('getId')
            ->willReturn($this->testedAccCreatedBy);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail');

        $this->factory
            ->expects($this->once())
            ->method('createNewAcc')
            ->willReturn($this->testedNewAcc);

        $this->writeAccRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($this->testedAccId);

        $this->readAccRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($this->testedAcc);

        $this->readAccRepository
            ->expects($this->once())
            ->method('findPollersByAccId')
            ->willReturn([$this->poller]);

        ($this->useCase)($this->testedAddAccRequest, $this->presenter);

        /** @var AddAccResponse $acc */
        $acc = $this->presenter->data;

        expect($acc)->toBeInstanceOf(AddAccResponse::class)
            ->and($acc->id)->toBe($this->testedAccId)
            ->and($acc->name)->toBe($this->testedAccName)
            ->and($acc->description)->toBe($this->testedAcc->getDescription())
            ->and($acc->createdAt->getTimestamp())->toBe($this->testedAccCreatedAt->getTimestamp())
            ->and($acc->updatedAt->getTimestamp())->toBeGreaterThanOrEqual(
                $this->testedAccUpdatedAt->getTimestamp()
            );
    }
);
