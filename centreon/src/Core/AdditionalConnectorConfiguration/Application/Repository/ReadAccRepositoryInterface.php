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

namespace Core\AdditionalConnectorConfiguration\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\Poller;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
use Core\Common\Domain\TrimmedString;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadAccRepositoryInterface
{
    /**
     * Determine if an Additional Connector (ACC) exists by its name.
     *
     * @param TrimmedString $name
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByName(TrimmedString $name): bool;

    /**
     * Find an Additional Connector (ACC).
     *
     * @param int $accId
     *
     * @throws \Throwable
     *
     * @return ?Acc
     */
    public function find(int $accId): ?Acc;

    /**
     * Find alls Additional Connectors (ACCs).
     *
     * @throws \Throwable
     *
     * @return Acc[]
     */
    public function findAll(): array;

    /**
     * Find all the pollers associated with any ACC of the specified type.
     *
     * @param Type $type
     *
     * @throws \Throwable
     *
     * @return Poller[]
     */
    public function findPollersByType(Type $type): array;

    /**
     * Find pollers NOT associated with any ACC of the specified type.
     *
     * @param Type $type
     * @param null|RequestParametersInterface $requestParameters
     *
     * @throws \Throwable
     *
     * @return Poller[]
     */
    public function findAvailablePollersByType(
        Type $type,
        ?RequestParametersInterface $requestParameters = null
    ): array;

    /**
     * Find pollers NOT associated with any ACC of the specified type (with ACL).
     *
     * @param Type $type
     * @param AccessGroup[] $accessGroups
     * @param null|RequestParametersInterface $requestParameters
     *
     * @throws \Throwable
     *
     * @return Poller[]
     */
    public function findAvailablePollersByTypeAndAccessGroup(
        Type $type,
        array $accessGroups,
        ?RequestParametersInterface $requestParameters = null
    ): array;

    /**
     * Find all the pollers associated with an ACC ID.
     *
     * @param int $accId
     *
     * @throws \Throwable
     *
     * @return Poller[]
     */
    public function findPollersByAccId(int $accId): array;

    /**
     * Find all ACC with request parameters.
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Throwable
     *
     * @return Acc[]
     */
    public function findByRequestParameters(RequestParametersInterface $requestParameters): array;

    /**
     * Find all ACC with request parameters.
     *
     * @param RequestParametersInterface $requestParameters
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return Acc[]
     */
    public function findByRequestParametersAndAccessGroups(
        RequestParametersInterface $requestParameters,
        array $accessGroups
    ): array;
}
