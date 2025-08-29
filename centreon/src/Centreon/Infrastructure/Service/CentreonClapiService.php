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

namespace Centreon\Infrastructure\Service;

use Centreon\Infrastructure\Service\Exception\NotFoundException;
use Centreon\Infrastructure\Service\Traits\ServiceContainerTrait;
use Psr\Container\ContainerInterface;
use ReflectionClass;

class CentreonClapiService implements ContainerInterface
{
    use ServiceContainerTrait;

    /**
     * Register service as CLI
     *
     * @param string $object
     * @throws NotFoundException
     * @return CentreonClapiService
     */
    public function add(string $object): self
    {
        $interface = CentreonClapiServiceInterface::class;
        $hasInterface = (new ReflectionClass($object))
            ->implementsInterface($interface);

        if ($hasInterface === false) {
            throw new NotFoundException(sprintf(_('Object %s must implement %s'), $object, $interface));
        }

        $name = strtolower($object::getName());
        $this->objects[$name] = $object;

        return $this;
    }
}
