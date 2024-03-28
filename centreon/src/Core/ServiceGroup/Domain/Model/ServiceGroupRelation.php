<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\ServiceGroup\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

class ServiceGroupRelation
{
    /**
     * @param int $serviceGroupId
     * @param int $serviceId (service ID or service template ID)
     * @param null|int $hostId
     * @param null|int $hostGroupId
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private int $serviceGroupId,
        private int $serviceId,
        private ?int $hostId = null,
        private ?int $hostGroupId = null,
    ) {
        Assertion::positiveInt($serviceGroupId, 'ServiceGroupRelation::serviceGroupId');
        Assertion::positiveInt($serviceId, 'ServiceGroupRelation::serviceId');
        if ($hostId !== null) {
            Assertion::positiveInt($hostId, 'ServiceGroupRelation::hostId');
        }
        if ($hostGroupId !== null) {
            Assertion::positiveInt($hostGroupId, 'ServiceGroupRelation::hostGroupId');
        }
    }

    /**
     * @return int
     */
    public function getServiceGroupId(): int
    {
        return $this->serviceGroupId;
    }

    /**
     * @return int
     */
    public function getServiceId(): int
    {
        return $this->serviceId;
    }

    /**
     * @return int
     */
    public function getHostId(): ?int
    {
        return $this->hostId;
    }

    /**
     * @return int|null
     */
    public function getHostGroupId(): ?int
    {
        return $this->hostGroupId;
    }

    /**
     * Extract all host IDs from an array of ServiceGroupRelation.
     *
     * @param ServiceGroupRelation[] $sgRelations
     *
     * @return int[]
     */
    public static function getHostIds(array $sgRelations): array
    {
        $hostIds = [];
        foreach ($sgRelations as $sgRel) {
            if ($sgRel->getHostId() !== null) {
                $hostIds[] = $sgRel->getHostId();
            }
        }

        return $hostIds;
    }

    /**
     * Extract all serviceGroup IDs from an array of ServiceGroupRelation.
     *
     * @param ServiceGroupRelation[] $sgRelations
     *
     * @return int[]
     */
    public static function getServiceGroupIds(array $sgRelations): array
    {
        return array_map(fn(ServiceGroupRelation $sgRel) => $sgRel->getServiceGroupId(), $sgRelations);
    }
}
