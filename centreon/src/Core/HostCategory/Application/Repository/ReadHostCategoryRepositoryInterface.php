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

namespace Core\HostCategory\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadHostCategoryRepositoryInterface
{
    /**
     * Find all host categories
     *
     * @param RequestParametersInterface|null $requestParameters
     * @return HostCategory[]
     * @throws \Throwable
     */
    public function findAll(?RequestParametersInterface $requestParameters): array;

    /**
     * Find all host categories by access groups
     *
     * @param AccessGroup[] $accessGroups
     * @param RequestParametersInterface|null $requestParameters
     * @return HostCategory[]
     * @throws \Throwable
     */
    public function findAllByAccessGroups(array $accessGroups, ?RequestParametersInterface $requestParameters): array;
}
