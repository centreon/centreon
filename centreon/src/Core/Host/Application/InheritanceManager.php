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

namespace Core\Host\Application;

use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;

/**
 * This class provide methods to help resolve and validate host inheritance.
 */
class InheritanceManager {
    public function __construct(
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
    ) {
    }

    /**
     * Return an ordered line of inheritance for a host or host template.
     * (This is a recursive method).
     *
     * @param int $hostId
     * @param array<array{parent_id:int,child_id:int,order:int}> $parents
     *
     * @return int[]
     */
    public static function findInheritanceLine(int $hostId, array $parents): array
    {
        $inheritanceLine = [];
        $directParents = array_filter($parents, (fn($row) => $row['child_id'] === $hostId));
        usort(
            $directParents,
            (static fn(array $parentA, array $parentB): int => $parentA['order'] <=> $parentB['order'])
        );

        foreach ($directParents as $parent) {
            $inheritanceLine = array_merge(
                $inheritanceLine,
                [$parent['parent_id']],
                self::findInheritanceLine($parent['parent_id'], $parents)
            );
        }

        return array_unique($inheritanceLine);
    }

    /**
     * Return false if a circular inheritance is detected, true otherwise.
     *
     * @param int $hostId
     * @param int[] $parents
     *
     * @return bool
     */
    public function isValidInheritanceTree(int $hostId, array $parents): bool
    {
        foreach ($parents as $templateId) {
            $ancestors = $this->readHostTemplateRepository->findParents($templateId);
            if (in_array($hostId, array_column($ancestors, 'parent_id'), true)) {

                return false;
            }
        }

        return true;
    }
}
