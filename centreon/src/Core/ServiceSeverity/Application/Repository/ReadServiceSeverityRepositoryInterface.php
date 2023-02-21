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

namespace Core\ServiceSeverity\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Common\Domain\TrimmedString;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\ServiceSeverity\Domain\Model\ServiceSeverity;

interface ReadServiceSeverityRepositoryInterface
{
    /**
     * Find all service severities.
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Throwable
     *
     * @return ServiceSeverity[]
     */
    public function findByRequestParameter(RequestParametersInterface $requestParameters): array;

    /**
     * Find all service severities by access groups.
     *
     * @param AccessGroup[] $accessGroups
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Throwable
     *
     * @return ServiceSeverity[]
     */
    public function findByRequestParameterAndAccessGroups(array $accessGroups, RequestParametersInterface $requestParameters): array;

    /**
     * Check existence of a service severity.
     *
     * @param int $serviceSeverityId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function exists(int $serviceSeverityId): bool;

    /**
     * Check existence of a service severity by access groups.
     *
     * @param int $serviceSeverityId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByAccessGroups(int $serviceSeverityId, array $accessGroups): bool;

    /**
     * Check existence of a service severity by name.
     *
     * @param TrimmedString $serviceSeverityName
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByName(TrimmedString $serviceSeverityName): bool;

    /**
     * Find one service severity.
     *
     * @param int $serviceSeverityId
     *
     * @return ServiceSeverity|null
     */
    public function findById(int $serviceSeverityId): ?ServiceSeverity;
}
