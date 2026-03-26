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
use CentreonRemote\Application\Webservice\CentreonWebServiceAbstract;
use CentreonWebService;
use Psr\Container\ContainerInterface;
use ReflectionClass;

class CentreonWebserviceService implements ContainerInterface
{
    use ServiceContainerTrait;

    /**
     * Add webservice from DI
     *
     * @param string $object
     * @throws NotFoundException
     * @return self
     */
    public function add(string $object): self
    {
        $centreonClass = CentreonWebService::class;
        $abstractClass = CentreonWebServiceAbstract::class;
        $ref = new ReflectionClass($object);
        $hasInterfaces = (
            $ref->isSubclassOf($centreonClass)
            || $ref->isSubclassOf($abstractClass)
        );

        if ($hasInterfaces === false) {
            throw new NotFoundException(
                sprintf(_('Object %s must extend %s class or %s class'), $object, $centreonClass, $abstractClass)
            );
        }

        $name = strtolower($object::getName());
        $this->objects[$name] = $object;

        return $this;
    }
}
