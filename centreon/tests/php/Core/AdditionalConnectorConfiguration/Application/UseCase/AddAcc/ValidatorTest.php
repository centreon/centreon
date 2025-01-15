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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\AdditionalConnectorConfiguration\Application\Exception\AccException;
use Core\AdditionalConnectorConfiguration\Application\Repository\ReadAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Application\UseCase\AddAcc\AddAccRequest;
use Core\AdditionalConnectorConfiguration\Application\UseCase\AddAcc\Validator;
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

    $this->request = new AddAccRequest();
    $this->request->name = 'my-ACC';
    $this->request->type = 'vmware_v6';
    $this->request->description = null;
    $this->request->pollers = [1];
    $this->request->parameters = [];

    $this->poller = new Poller(1, 'poller-name');
});

it('should throw an exception when the name is invalid', function (): void {
    $this->readAccRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(true);

    $this->validator->validateNameOrFail($this->request);
})->throws(AccException::nameAlreadyExists('my-ACC')->getMessage());

it('should throw an exception when the pollers list is empty', function (): void {
    $this->request->pollers = [];
    $this->validator->validatePollersOrFail($this->request);
})->throws(AccException::arrayCanNotBeEmpty('pollers')->getMessage());

it('should throw an exception when a poller ID does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readMonitoringServerRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validator->validatePollersOrFail($this->request);
})->throws(AccException::idsDoNotExist('pollers', [1])->getMessage());

it('should throw an exception when the ACC type is not valid', function (): void {
    $this->request->type = '';
    $this->validator->validateTypeOrFail($this->request);
})->throws((new ValueError('"" is not a valid backing value for enum Core\AdditionalConnectorConfiguration\Domain\Model\Type'))->getMessage());

it('should throw an exception when the ACC type is already associated to one of the pollers', function (): void {
    $this->readAccRepository
        ->expects($this->once())
        ->method('findPollersByType')
        ->willReturn([$this->poller]);

    $this->validator->validateTypeOrFail($this->request);
})->throws(AccException::alreadyAssociatedPollers(Type::VMWARE_V6, [1])->getMessage());
