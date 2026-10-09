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

namespace Tests\Core\Service\Infrastructure\Repository;

use Centreon\Domain\Repository\RepositoryException;
use Centreon\Infrastructure\DatabaseConnection;
use Core\ActionLog\Application\Repository\WriteActionLogRepositoryInterface;
use Core\ActionLog\Domain\Model\ActionLog;
use Core\Common\Application\CurrentUserIdResolverInterface;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\Service\Infrastructure\Repository\DbWriteServiceActionLogRepository;

beforeEach(function (): void {
    $this->writeServiceRepository = $this->createMock(WriteServiceRepositoryInterface::class);
    $this->currentUserIdResolver = $this->createMock(CurrentUserIdResolverInterface::class);
    $this->readServiceRepository = $this->createMock(ReadServiceRepositoryInterface::class);
    $this->writeActionLogRepository = $this->createMock(WriteActionLogRepositoryInterface::class);
    $this->repository = new DbWriteServiceActionLogRepository(
        $this->writeServiceRepository,
        $this->currentUserIdResolver,
        $this->readServiceRepository,
        $this->writeActionLogRepository,
        $this->createMock(DatabaseConnection::class),
    );

    $this->readServiceRepository
        ->method('findByHostIdAndServiceTemplateId')
        ->with(1, 2)
        ->willReturn([['id' => 10, 'name' => 'service-10']]);
});

it('should log the deletion with the current user as author', function (): void {
    $this->currentUserIdResolver->method('getUserId')->willReturn(7);

    $this->writeActionLogRepository
        ->expects($this->once())
        ->method('addAction')
        ->with($this->callback(
            static fn (ActionLog $actionLog): bool => $actionLog->getContactId() === 7
                && $actionLog->getObjectId() === 10
        ));

    $this->repository->deleteByHostIdAndServiceTemplateId(1, 2);
});

// ActionLog does not accept a null contact ID on this branch.
it('should throw instead of writing an action log without author', function (): void {
    $this->currentUserIdResolver->method('getUserId')->willReturn(null);

    // The deletion itself already ran: the caller's transaction is what keeps the data consistent.
    $this->writeServiceRepository
        ->expects($this->once())
        ->method('deleteByHostIdAndServiceTemplateId')
        ->with(1, 2);
    $this->writeActionLogRepository->expects($this->never())->method('addAction');

    $this->repository->deleteByHostIdAndServiceTemplateId(1, 2);
})->throws(RepositoryException::class);
