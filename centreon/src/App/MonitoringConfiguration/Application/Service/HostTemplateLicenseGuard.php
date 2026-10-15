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

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Exception\UnlicensedHostTemplatesException;
use App\Shared\Domain\Collection;

/**
 * Once the license host limit is reached, a host can no longer be given a template coming from a
 * paid Monitoring Connector. The templates it already has are kept: they were given while the
 * limit allowed it.
 */
final readonly class HostTemplateLicenseGuard
{
    public function __construct(
        private UnlicensedHostTemplates $unlicensedHostTemplates,
    ) {
    }

    /**
     * @param Collection<HostTemplateId> $templateIds the templates the host is given
     * @param Collection<HostTemplateId>|null $currentTemplateIds the templates the host already has
     *
     * @throws UnlicensedHostTemplatesException
     */
    public function assertLicensed(Collection $templateIds, ?Collection $currentTemplateIds = null): void
    {
        $currentIds = $currentTemplateIds instanceof Collection ? $this->toIntegers($currentTemplateIds) : [];
        $addedIds = array_values(array_diff($this->toIntegers($templateIds), $currentIds));
        if ($addedIds === []) {
            return;
        }

        $unlicensedIds = array_values(array_intersect($addedIds, $this->toIntegers($this->unlicensedHostTemplates->findIds())));
        if ($unlicensedIds !== []) {
            throw new UnlicensedHostTemplatesException($unlicensedIds);
        }
    }

    /**
     * @param Collection<HostTemplateId> $ids
     *
     * @return list<int>
     */
    private function toIntegers(Collection $ids): array
    {
        return array_values(array_map(static fn (HostTemplateId $id): int => $id->value, $ids->toArray()));
    }
}
