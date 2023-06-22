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

namespace Tests\Core\Host\Application;

use Core\Host\Application\InheritanceManager;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;

it('return inheritance line in the expected order', function (): void {
    $hostId = 1;
    $parents = [
        ['child_id'=> 5, 'parent_id'=> 9, 'order' => 2],
        ['child_id'=> 1, 'parent_id'=> 2, 'order' => 0],
        ['child_id'=> 2, 'parent_id'=> 3, 'order' => 0],
        ['child_id'=> 2, 'parent_id'=> 4, 'order' => 1],
        ['child_id'=> 3, 'parent_id'=> 4, 'order' => 0],
        ['child_id'=> 4, 'parent_id'=> 5, 'order' => 0],
        ['child_id'=> 5, 'parent_id'=> 8, 'order' => 1],
        ['child_id'=> 5, 'parent_id'=> 6, 'order' => 0],
        ['child_id'=> 6, 'parent_id'=> 7, 'order' => 0],
    ];
    $inheritanceLine = InheritanceManager::findInheritanceLine($hostId, $parents);

    expect($inheritanceLine)->toBe([2, 3, 4, 5, 6, 7, 8, 9]);
});

it('return false when a circular inheritance is detected', function (): void {
    $hostId = 1;
    $parents = [2, 5];

    $manager = new InheritanceManager(
        $this->repository = $this->createMock(ReadHostTemplateRepositoryInterface::class)
    );

    $this->repository
        ->expects($this->exactly(2))
        ->method('findParents')
        ->willReturnMap([
            [
                2,
                [
                    ['child_id' => 2, 'parent_id' => 3, 'order' => 0],
                    ['child_id' => 3, 'parent_id' => 4, 'order' => 0],
                ]
            ],
            [
                5,
                [
                    ['child_id' => 5, 'parent_id' => 6, 'order' => 0],
                    ['child_id' => 6, 'parent_id' => 1, 'order' => 0],
                ]
            ],
        ]);

    expect($manager->isValidInheritanceTree($hostId, $parents))->toBe(false);
});

it('return true when an inheritance tree is valid', function (): void {
    $hostId = 1;
    $parents = [2, 5];

    $manager = new InheritanceManager(
        $this->repository = $this->createMock(ReadHostTemplateRepositoryInterface::class)
    );

    $this->repository
        ->expects($this->exactly(2))
        ->method('findParents')
        ->willReturnMap([
            [
                2,
                [
                    ['child_id' => 2, 'parent_id' => 3, 'order' => 0],
                    ['child_id' => 3, 'parent_id' => 4, 'order' => 0],
                ]
            ],
            [
                5,
                [
                    ['child_id' => 5, 'parent_id' => 6, 'order' => 0],
                    ['child_id' => 6, 'parent_id' => 7, 'order' => 0],
                ]
            ],
        ]);

    expect($manager->isValidInheritanceTree($hostId, $parents))->toBe(true);
});
