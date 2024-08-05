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

namespace Tests\Core\AdditionalConnectorConfiguration\Application\UseCase\UpdateAcc\Validation;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\AdditionalConnectorConfiguration\Application\Exception\AccException;
use Core\AdditionalConnectorConfiguration\Application\Repository\ReadAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Application\UseCase\UpdateAcc\UpdateAccRequest;
use Core\AdditionalConnectorConfiguration\Application\UseCase\UpdateAcc\Validator;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\AccParametersInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Poller;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use ValueError;

beforeEach(function (): void {
    $this->validator = new Validator(
        readAccRepository: $this->readAccRepository = $this->createMock(ReadAccRepositoryInterface::class),
        user: $this->user = $this->createMock(ContactInterface::class),
        readAccessGroupRepository: $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        readMonitoringServerRepository: $this->readMonitoringServerRepository = $this->createMock(ReadMonitoringServerRepositoryInterface::class),
        parametersValidators: new \ArrayIterator([]),
    );

    $this->acc = new Acc(
        id: 1,
        name: 'my-acc',
        type: Type::VMWARE_V6,
        createdBy: 1,
        updatedBy: 1,
        createdAt: new \DateTimeImmutable(),
        updatedAt: new \DateTimeImmutable(),
        parameters: $this->createMock(AccParametersInterface::class)
    );

    $this->request = new UpdateAccRequest();
    $this->request->name = 'my-ACC';
    $this->request->type = 'vmware_v6';
    $this->request->description = null;
    $this->request->pollers = [1];
    $this->request->parameters = [];

    $this->poller = new Poller(1, 'poller-name');
    $this->pollerBis = new Poller(2, 'poller-name-bis');
});

it('should throw an exception when the name is invalid', function (): void {
    $this->readAccRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(true);

    $this->validator->validateNameOrFail($this->request, $this->acc);
})->throws(AccException::nameAlreadyExists('my-ACC')->getMessage());

it('should throw an exception when the pollers list is empty', function (): void {
    $this->request->pollers = [];
    $this->validator->validatePollersOrFail($this->request, $this->acc);
})->throws(AccException::arrayCanNotBeEmpty('pollers')->getMessage());

it('should throw an exception when the ACC type is changed', function (): void {
    $this->request->type = '';
    $this->validator->validateTypeOrFail($this->request, $this->acc);
})->skip(true, "Cannot be tested as long as there is only one supported type");
//throws(AccException::typeChangeNotAllowed()->getMessage());

it('should throw an exception when a poller ID does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readMonitoringServerRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validator->validatePollersOrFail($this->request, $this->acc);
})->throws(AccException::idsDoNotExist('pollers', [1])->getMessage());

it('should throw an exception when the ACC type is already associated to one of the pollers', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readMonitoringServerRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->readAccRepository
        ->expects($this->once())
        ->method('findPollersByAccId')
        ->willReturn([$this->pollerBis]);

    $this->readAccRepository
        ->expects($this->once())
        ->method('findPollersByType')
        ->willReturn([$this->poller]);

    $this->validator->validatePollersOrFail($this->request, $this->acc);
})->throws(AccException::alreadyAssociatedPollers(Type::VMWARE_V6, [1])->getMessage());
