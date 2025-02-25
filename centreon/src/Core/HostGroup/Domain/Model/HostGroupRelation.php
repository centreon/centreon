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

namespace Core\HostGroup\Domain\Model;

use Core\Common\Domain\SimpleEntity;
use Core\ResourceAccess\Domain\Model\Rule;

/**
 * This class is designed to represent the relation between a hostgroup and its hosts and resource access.
 *
 * It could be improved 
 */
final class HostGroupRelation
{
    /**
     *
     * @param HostGroup $hostGroup
     * @param SimpleEntity[] $hosts
     * @param Rule[] $resourceAccessRules
     */
    public function __construct(
        private readonly HostGroup $hostGroup,
        private readonly array $hosts = [],
        private readonly array $resourceAccessRules = []
    ) {
    }

    public function getHostGroup(): HostGroup
    {
        return $this->hostGroup;
    }

    /**
     *
     * @return SimpleEntity[]
     */
    public function getHosts(): array
    {
        return $this->hosts;
    }

    /**
     * @return Rule[]
     */
    public function getResourceAccessRules(): array
    {
        return $this->resourceAccessRules;
    }
}
