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

namespace Core\Host\Domain\Model;


/**
 * This class purpose is to sort the parents of a host (or host template).
 */
final class HostInheritance {
    /**
     * Return an ordered line of inheritance for a host template.
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
        usort($directParents, (fn($a, $b) => $a['order'] <=> $b['order']));

        foreach ($directParents as $parent) {
            $inheritanceLine = array_merge(
                $inheritanceLine,
                [$parent['parent_id']],
                self::findInheritanceLine($parent['parent_id'], $parents)
            );
        }

        return array_unique($inheritanceLine);
    }
}
