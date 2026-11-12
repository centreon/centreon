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

namespace Core\HostTemplate\Application;

use Core\Common\Domain\Exception\RepositoryException;
use Core\HostTemplate\Application\Repository\ReadHostLicenseRepositoryInterface;

/**
 * Once the license host limit is reached, a host or a host template can no longer be given a template
 * coming from a Monitoring Connector that is not free of license, nor a template inheriting from it.
 * The templates it already has are kept: they were given while the limit allowed it.
 */
final class UnlicensedHostTemplatesFinder
{
    public function __construct(
        private readonly ReadHostLicenseRepositoryInterface $readHostLicenseRepository,
    ) {
    }

    /**
     * @param int[] $templateIds the templates the host or the host template is given
     * @param int[] $currentTemplateIds the templates it already has
     *
     * @throws RepositoryException
     *
     * @return int[] the templates among the added ones that the license no longer allows
     */
    public function findAmongAdded(array $templateIds, array $currentTemplateIds = []): array
    {
        $addedIds = array_diff($templateIds, $currentTemplateIds);
        if ($addedIds === []) {
            return [];
        }

        if (! $this->isHostLimitReached()) {
            return [];
        }

        return array_values(
            array_intersect($addedIds, $this->readHostLicenseRepository->findPaidConnectorTemplateIds())
        );
    }

    /**
     * @throws RepositoryException
     *
     * @return bool
     */
    private function isHostLimitReached(): bool
    {
        if (! $this->readHostLicenseRepository->isValid()) {
            return true;
        }

        $maxHosts = $this->readHostLicenseRepository->getMaxHosts();

        return $maxHosts !== null && $maxHosts <= $this->readHostLicenseRepository->countHosts();
    }
}
