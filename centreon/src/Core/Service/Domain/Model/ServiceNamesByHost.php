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

declare(strict_types = 1);

namespace Core\Service\Domain\Model;

use Core\Common\Domain\TrimmedString;

class ServiceNamesByHost
{
    /** @var list<string> */
    private array $names = [];

    /**
     * @param int $hostId
     * @param list<string> $servicesName
     */
    public function __construct(private readonly int $hostId, array $servicesName)
    {
        $this->setServicesName($servicesName);
    }

    /**
     * @param TrimmedString $serviceName
     *
     * @return bool
     */
    public function contains(TrimmedString $serviceName): bool
    {
        return in_array($serviceName->value, $this->names, true);
    }

    /**
     * @return int
     */
    public function getHostId(): int{
        return $this->hostId;
    }

    /**
     * @template T
     *
     * @param list<T> $servicesName
     */
    private function setServicesName(array $servicesName): void
    {
        $this->names = [];
        foreach ($servicesName as $serviceName) {
            $this->names[] = (string) $serviceName;
        }
    }
}
