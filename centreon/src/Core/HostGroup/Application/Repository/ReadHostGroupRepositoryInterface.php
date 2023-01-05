<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Core\HostGroup\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadHostGroupRepositoryInterface
{
    /**
     * Find All host groups without acl.
     *
     * @param RequestParametersInterface|null $requestParameters
     *
     * @throws \Throwable
     *
     * @return list<HostGroup>
     */
    public function findAll(?RequestParametersInterface $requestParameters): array;

    /**
     * Find All host groups with access groups.
     *
     * @param RequestParametersInterface|null $requestParameters
     * @param list<AccessGroup> $accessGroups
     *
     * @throws \Throwable
     *
     * @return list<HostGroup>
     */
    public function findAllByAccessGroups(?RequestParametersInterface $requestParameters, array $accessGroups): array;
}
