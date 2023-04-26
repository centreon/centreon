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

namespace Core\HostSeverity\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Common\Domain\TrimmedString;
use Core\HostSeverity\Domain\Model\HostSeverity;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadHostSeverityRepositoryInterface
{
    /**
     * Find all host severities.
     *
     * @param RequestParametersInterface|null $requestParameters
     *
     * @throws \Throwable
     *
     * @return HostSeverity[]
     */
    public function findAll(?RequestParametersInterface $requestParameters): array;

    /**
     * Find all host severities by access groups.
     *
     * @param AccessGroup[] $accessGroups
     * @param RequestParametersInterface|null $requestParameters
     *
     * @throws \Throwable
     *
     * @return HostSeverity[]
     */
    public function findAllByAccessGroups(array $accessGroups, ?RequestParametersInterface $requestParameters): array;

    /**
     * Check existence of a host severity.
     *
     * @param int $hostSeverityId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function exists(int $hostSeverityId): bool;

    /**
     * Check existence of a host severity by access groups.
     *
     * @param int $hostSeverityId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByAccessGroups(int $hostSeverityId, array $accessGroups): bool;

    /**
     * Check existence of a host severity by name.
     *
     * @param TrimmedString $hostSeverityName
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByName(TrimmedString $hostSeverityName): bool;

    /**
     * Find one host severity.
     *
     * @param int $hostSeverityId
     *
     * @return HostSeverity|null
     */
    public function findById(int $hostSeverityId): ?HostSeverity;
}
