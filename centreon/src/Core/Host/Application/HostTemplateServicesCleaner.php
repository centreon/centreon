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

namespace Core\Host\Application;

use Centreon\Domain\Log\LoggerTrait;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;

/**
 * Deletes the services a host got from the host templates it is no longer linked to.
 *
 * Shared by the host API and the legacy mass change of hosts.
 */
class HostTemplateServicesCleaner
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadServiceTemplateRepositoryInterface $readServiceTemplateRepository,
        private readonly WriteServiceRepositoryInterface $writeServiceRepository,
    ) {
    }

    /**
     * Clean up services from templates that were removed from a host.
     *
     * @param int $hostId
     * @param int[] $previousDirectParentIds Direct parent template IDs before the update
     * @param int[] $newDirectParentIds Direct parent template IDs after the update
     */
    public function cleanServicesFromRemovedTemplates(
        int $hostId,
        array $previousDirectParentIds,
        array $newDirectParentIds,
    ): void {
        $removedTemplateIds = array_values(array_diff($previousDirectParentIds, $newDirectParentIds));
        if ($removedTemplateIds === []) {
            return;
        }

        $this->info('Clean up services from removed templates', [
            'host_id' => $hostId,
            'removed_template_ids' => $removedTemplateIds,
        ]);

        // Expand remaining templates to include their full inheritance chains
        $allRemainingIds = $this->expandTemplateChain($newDirectParentIds);

        // Process each removed template and its inheritance chain
        $visited = [];
        foreach ($removedTemplateIds as $removedTemplateId) {
            $this->deleteServicesFromTemplate($hostId, $removedTemplateId, $allRemainingIds, $visited);
        }
    }

    /**
     * Recursively process a template and its parents to delete services
     * that are no longer provided by any remaining template.
     *
     * @param int $hostId
     * @param int $templateId
     * @param int[] $allRemainingIds Expanded remaining template IDs
     * @param array<int, bool> $visited Anti-loop tracker
     */
    private function deleteServicesFromTemplate(
        int $hostId,
        int $templateId,
        array $allRemainingIds,
        array &$visited,
    ): void {
        if (isset($visited[$templateId])) {
            return;
        }
        $visited[$templateId] = true;

        // Find service templates linked to this host template
        $serviceTemplateIds = $this->readServiceTemplateRepository->findIdsByHostTemplateId($templateId);

        foreach ($serviceTemplateIds as $serviceTemplateId) {
            // Only delete if this service template is not provided by any remaining template
            if (! $this->readServiceTemplateRepository->isLinkedToAnyHostTemplate($serviceTemplateId, $allRemainingIds)) {
                $this->writeServiceRepository->deleteByHostIdAndServiceTemplateId($hostId, $serviceTemplateId);
            }
        }

        // Recursively process this template's own parent templates
        $parentChain = $this->readHostRepository->findParents($templateId);
        $directParentIds = [];
        foreach ($parentChain as $row) {
            if ((int) $row['child_id'] === $templateId) {
                $directParentIds[] = (int) $row['parent_id'];
            }
        }

        foreach ($directParentIds as $parentId) {
            $this->deleteServicesFromTemplate($hostId, $parentId, $allRemainingIds, $visited);
        }
    }

    /**
     * Expand a list of template IDs to include their full inheritance chains.
     *
     * @param int[] $templateIds
     *
     * @return int[]
     */
    private function expandTemplateChain(array $templateIds): array
    {
        $allIds = [];
        foreach ($templateIds as $templateId) {
            $allIds[] = $templateId;
            $parentChain = $this->readHostRepository->findParents($templateId);
            foreach ($parentChain as $row) {
                $allIds[] = (int) $row['parent_id'];
            }
        }

        return array_values(array_unique($allIds));
    }
}
