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

namespace Tests\Core\AdditionalConnectorConfiguration\Application\UseCase\UpdateAcc;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\AdditionalConnectorConfiguration\Application\Exception\AccException;
use Core\AdditionalConnectorConfiguration\Application\Factory\AccFactory;
use Core\AdditionalConnectorConfiguration\Application\Repository\ReadAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Application\Repository\WriteAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Application\UseCase\UpdateAcc\UpdateAcc;
use Core\AdditionalConnectorConfiguration\Application\UseCase\UpdateAcc\UpdateAccRequest;
use Core\AdditionalConnectorConfiguration\Application\UseCase\UpdateAcc\Validator;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\AccParametersInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Poller;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Common\Infrastructure\FeatureFlags;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new DefaultPresenter($this->presenterFormatter);

    $this->useCase = new UpdateAcc(
        $this->readAccRepository = $this->createMock(ReadAccRepositoryInterface::class),
        $this->writeAccRepository = $this->createMock(WriteAccRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readMonitoringServerRepository = $this->createMock(ReadMonitoringServerRepositoryInterface::class),
        $this->validator = $this->createMock(Validator::class),
        $this->factory = $this->createMock(AccFactory::class),
        $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->flags = new FeatureFlags(false, ''),
        $this->writeVaultAccRepositories = new \ArrayIterator([]),
        $this->readVaultAccRepositories = new \ArrayIterator([])
    );

    $this->request = new UpdateAccRequest();
    $this->request->name = 'acc edited';
    $this->request->type = 'vmware_v6';
    $this->request->description = 'toto';
    $this->request->pollers = [1];
    $this->request->parameters = [
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

    $this->acc = new Acc(
        id: $this->accId = 1,
        name: $this->accName = 'acc-name',
        type: $this->accType = Type::VMWARE_V6,
        createdBy: $this->accCreatedBy = 2,
        updatedBy: $this->accUpdatedBy = $this->accCreatedBy,
        createdAt: $this->accCreatedAt = new \DateTimeImmutable('2023-05-09T12:00:00+00:00'),
        updatedAt: $this->accUpdatedAt = $this->accCreatedAt,
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

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->getResponseStatus()->getMessage())
            ->toBe(AccException::accessNotAllowed()->getMessage());
    }
);

it(
    'should present an ErrorResponse when a Exception is thrown',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->readAccRepository
            ->expects($this->once())
            ->method('find')
            ->willThrowException(new \Exception());

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()->getMessage())
            ->toBe(AccException::updateAcc()->getMessage());
    }
);

it(
    'should present an ErrorResponse when a generic exception is thrown',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->readAccRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($this->acc);

        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail');

        $this->writeAccRepository
            ->expects($this->once())
            ->method('update')
            ->willThrowException(new \Exception());

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()->getMessage())
            ->toBe(AccException::updateAcc()->getMessage());
    }
);

it(
    'should present a InvalidArgumentResponse when a field value is not valid',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->readAccRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($this->acc);

        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail');

        $this->request->name = '';
        $expectedException = AssertionException::notEmptyString('Acc::name');

        $this->factory
            ->expects($this->once())
            ->method('updateAcc')
            ->willThrowException($expectedException);

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($this->presenter->getResponseStatus()->getMessage())
            ->toBe($expectedException->getMessage());
    }
);

it(
    'should present a UpdateAccResponse when no error occurs',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->readAccRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($this->acc);

        $this->user
            ->expects($this->any())
            ->method('getId')
            ->willReturn($this->accCreatedBy);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail');

        $this->factory
            ->expects($this->once())
            ->method('updateAcc');

        $this->writeAccRepository
            ->expects($this->once())
            ->method('update');

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
    }
);
