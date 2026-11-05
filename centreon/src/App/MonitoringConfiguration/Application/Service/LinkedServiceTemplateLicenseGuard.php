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

namespace App\MonitoringConfiguration\Application\Service;

use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Exception\UnlicensedServiceTemplatesException;
use App\Shared\Domain\Collection;

/**
 * Once the license host limit is reached, a host template cannot be given a service template of the
 * Monitoring Connectors; the ones it already has are kept.
 */
final readonly class LinkedServiceTemplateLicenseGuard
{
    public function __construct(
        private UnlicensedServiceTemplates $unlicensedServiceTemplates,
    ) {
    }

    /**
     * @param Collection<ServiceTemplateId> $serviceTemplateIds the service templates the template is given
     * @param Collection<ServiceTemplateId>|null $currentServiceTemplateIds the ones it already has
     *
     * @throws UnlicensedServiceTemplatesException
     */
    public function assertLicensed(Collection $serviceTemplateIds, ?Collection $currentServiceTemplateIds = null): void
    {
        $currentIds = $currentServiceTemplateIds instanceof Collection ? $this->toIntegers($currentServiceTemplateIds) : [];
        $addedIds = array_values(array_diff($this->toIntegers($serviceTemplateIds), $currentIds));
        if ($addedIds === []) {
            return;
        }

        $unlicensedIds = array_values(array_intersect($addedIds, $this->toIntegers($this->unlicensedServiceTemplates->findIds())));
        if ($unlicensedIds !== []) {
            throw new UnlicensedServiceTemplatesException($unlicensedIds);
        }
    }

    /**
     * @param Collection<ServiceTemplateId> $ids
     *
     * @return list<int>
     */
    private function toIntegers(Collection $ids): array
    {
        return array_values(array_map(static fn (ServiceTemplateId $id): int => $id->value, $ids->toArray()));
    }
}
