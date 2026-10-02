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

namespace Tests\Core\Host\Application;

use Core\Host\Application\HostTemplateServicesCleaner;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;

beforeEach(function (): void {
    $this->cleaner = new HostTemplateServicesCleaner(
        readHostRepository: $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class),
        readServiceTemplateRepository: $this->readServiceTemplateRepository = $this->createMock(ReadServiceTemplateRepositoryInterface::class),
        writeServiceRepository: $this->writeServiceRepository = $this->createMock(WriteServiceRepositoryInterface::class),
    );
    $this->hostId = 1;
});

it('should do nothing when no template is removed', function (): void {
    $this->readHostRepository->expects($this->never())->method('findParents');
    $this->writeServiceRepository->expects($this->never())->method('deleteByHostIdAndServiceTemplateId');

    $this->cleaner->cleanServicesFromRemovedTemplates($this->hostId, [2, 3], [3, 2, 4]);
});

it('should delete the services of a removed template not provided by a remaining one', function (): void {
    // Templates [2, 3, 4] become [2, 3]: template 4 is removed, template 2 inherits from 3.
    $this->readHostRepository
        ->expects($this->exactly(3))
        ->method('findParents')
        ->willReturnMap([
            [2, [['child_id' => 2, 'parent_id' => 3, 'order' => 0]]],
            [3, []],
            [4, []],
        ]);
    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('findIdsByHostTemplateId')
        ->with(4)
        ->willReturn([10, 11]);
    $this->readServiceTemplateRepository
        ->expects($this->exactly(2))
        ->method('isLinkedToAnyHostTemplate')
        ->willReturnMap([
            [10, [2, 3], false],
            [11, [2, 3], true],
        ]);
    $this->writeServiceRepository
        ->expects($this->once())
        ->method('deleteByHostIdAndServiceTemplateId')
        ->with($this->hostId, 10);

    $this->cleaner->cleanServicesFromRemovedTemplates($this->hostId, [2, 3, 4], [2, 3]);
});

it('should also delete the services of the parents of a removed template', function (): void {
    // Templates [4] become [5]: template 4 inherits from 6, so services of 6 go too.
    $this->readHostRepository
        ->method('findParents')
        ->willReturnMap([
            [5, []],
            [4, [['child_id' => 4, 'parent_id' => 6, 'order' => 0]]],
            [6, []],
        ]);
    $this->readServiceTemplateRepository
        ->method('findIdsByHostTemplateId')
        ->willReturnMap([
            [4, [10]],
            [6, [20]],
        ]);
    $this->readServiceTemplateRepository
        ->method('isLinkedToAnyHostTemplate')
        ->willReturn(false);

    $deleted = [];
    $this->writeServiceRepository
        ->expects($this->exactly(2))
        ->method('deleteByHostIdAndServiceTemplateId')
        ->willReturnCallback(function (int $hostId, int $serviceTemplateId) use (&$deleted): void {
            $deleted[] = [$hostId, $serviceTemplateId];
        });

    $this->cleaner->cleanServicesFromRemovedTemplates($this->hostId, [4], [5]);

    expect($deleted)->toBe([[$this->hostId, 10], [$this->hostId, 20]]);
});

it('should delete every service of the removed templates when no template remains', function (): void {
    $this->readHostRepository->method('findParents')->willReturn([]);
    $this->readServiceTemplateRepository->method('findIdsByHostTemplateId')->with(4)->willReturn([10]);
    $this->readServiceTemplateRepository
        ->expects($this->once())
        ->method('isLinkedToAnyHostTemplate')
        ->with(10, [])
        ->willReturn(false);
    $this->writeServiceRepository
        ->expects($this->once())
        ->method('deleteByHostIdAndServiceTemplateId')
        ->with($this->hostId, 10);

    $this->cleaner->cleanServicesFromRemovedTemplates($this->hostId, [4], []);
});

it('should stop on an inheritance loop between templates', function (): void {
    // Templates [4] become [5]: template 4 inherits from 6, which inherits back from 4.
    $this->readHostRepository
        ->method('findParents')
        ->willReturnMap([
            [5, []],
            [4, [['child_id' => 4, 'parent_id' => 6, 'order' => 0], ['child_id' => 6, 'parent_id' => 4, 'order' => 0]]],
            [6, [['child_id' => 6, 'parent_id' => 4, 'order' => 0], ['child_id' => 4, 'parent_id' => 6, 'order' => 0]]],
        ]);
    $visitedTemplateIds = [];
    $this->readServiceTemplateRepository
        ->expects($this->exactly(2))
        ->method('findIdsByHostTemplateId')
        ->willReturnCallback(function (int $templateId) use (&$visitedTemplateIds): array {
            $visitedTemplateIds[] = $templateId;

            return [];
        });

    $this->cleaner->cleanServicesFromRemovedTemplates($this->hostId, [4], [5]);

    expect($visitedTemplateIds)->toBe([4, 6]);
});

it('should keep the services of a parent still inherited through a remaining template', function (): void {
    // Templates [4] become [5]: both inherit from 6, so the services of 6 stay.
    $this->readHostRepository
        ->method('findParents')
        ->willReturnMap([
            [5, [['child_id' => 5, 'parent_id' => 6, 'order' => 0]]],
            [4, [['child_id' => 4, 'parent_id' => 6, 'order' => 0]]],
            [6, []],
        ]);
    $this->readServiceTemplateRepository
        ->method('findIdsByHostTemplateId')
        ->willReturnMap([
            [4, [10]],
            [6, [20]],
        ]);
    $this->readServiceTemplateRepository
        ->method('isLinkedToAnyHostTemplate')
        ->willReturnCallback(
            static fn (int $serviceTemplateId, array $hostTemplateIds): bool => $serviceTemplateId === 20
                && in_array(6, $hostTemplateIds, true)
        );
    $this->writeServiceRepository
        ->expects($this->once())
        ->method('deleteByHostIdAndServiceTemplateId')
        ->with($this->hostId, 10);

    $this->cleaner->cleanServicesFromRemovedTemplates($this->hostId, [4], [5]);
});
