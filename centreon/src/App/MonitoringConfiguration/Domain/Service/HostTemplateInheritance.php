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

namespace App\MonitoringConfiguration\Domain\Service;

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Exception\CircularHostTemplateInheritanceException;
use App\Shared\Domain\Collection;

/**
 * The inheritance graph of the host templates must stay free of loops.
 */
final readonly class HostTemplateInheritance
{
    /**
     * Refuses the parents that are the template itself or one of its descendants. The descendants are
     * walked with a queue and a visited set: no recursion, so no depth limit, and a loop already stored
     * elsewhere in the graph cannot keep the walk going.
     *
     * @param Collection<HostTemplateId> $parentIds the parents the template is about to have
     * @param array<int, list<int>> $parentIdsByTemplate every stored edge, template id => its parent ids
     *
     * @throws CircularHostTemplateInheritanceException
     */
    public function assertNoLoop(HostTemplateId $self, Collection $parentIds, array $parentIdsByTemplate): void
    {
        $childIdsByTemplate = [];
        foreach ($parentIdsByTemplate as $childId => $templateParentIds) {
            foreach ($templateParentIds as $parentId) {
                $childIdsByTemplate[$parentId][] = $childId;
            }
        }

        $reached = [$self->value => true];
        $queue = [$self->value];
        while ($queue !== []) {
            $id = array_pop($queue);
            foreach ($childIdsByTemplate[$id] ?? [] as $childId) {
                if (! isset($reached[$childId])) {
                    $reached[$childId] = true;
                    $queue[] = $childId;
                }
            }
        }

        $offendingIds = [];
        foreach ($parentIds as $parentId) {
            if (isset($reached[$parentId->value])) {
                $offendingIds[] = $parentId->value;
            }
        }

        if ($offendingIds !== []) {
            throw new CircularHostTemplateInheritanceException(array_values(array_unique($offendingIds)));
        }
    }
}
