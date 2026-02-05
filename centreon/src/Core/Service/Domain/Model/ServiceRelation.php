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

namespace Core\Service\Domain\Model;

final readonly class ServiceRelation
{
    /**
     * @param int $serviceId
     * @param int[] $hostGroupIds
     * @param int[] $hostIds
     * @param int[] $serviceGroupIds
     */
    public function __construct(
        private int $serviceId,
        private array $hostGroupIds = [],
        private array $hostIds = [],
        private array $serviceGroupIds = [],
    ) {
    }

    public function getServiceId(): int
    {
        return $this->serviceId;
    }

    /**
     * @return int[]
     */
    public function getHostGroupIds(): array
    {
        return $this->hostGroupIds;
    }

    /**
     * @return int[]
     */
    public function getHostIds(): array
    {
        return $this->hostIds;
    }

    /**
     * @return int[]
     */
    public function getServiceGroupIds(): array
    {
        return $this->serviceGroupIds;
    }

    public function hasOnlyOneHostGroup(): bool
    {
        return count($this->hostGroupIds) === 1;
    }

    public function hasOnlyOneHost(): bool
    {
        return count($this->hostIds) === 1;
    }

    public function hasOnlyOneServiceGroup(): bool
    {
        return count($this->serviceGroupIds) === 1;
    }
}
