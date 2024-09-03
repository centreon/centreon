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

namespace Core\User\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\User\Domain\Model\User;

interface ReadUserRepositoryInterface
{
    /**
     * Find configured users.
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Throwable
     *
     * @return User[]
     */
    public function findAllByRequestParameters(RequestParametersInterface $requestParameters): array;

    /**
     * Find configured users that belong to given access groups.
     *
     * @param AccessGroup[] $accessGroups
     * @param RequestParametersInterface|null $requestParameters
     *
     * @throws \Throwable
     *
     * @return User[]
     */
    public function findByAccessGroupsAndRequestParameters(
        array $accessGroups,
        ?RequestParametersInterface $requestParameters
    ): array;

    /**
     * Find a user by its ID.
     *
     * @param int $userId
     *
     * @throws \Throwable
     */
    public function find(int $userId): ?User;
}
