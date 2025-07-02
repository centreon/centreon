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

namespace Centreon\Infrastructure\Webservice;

use Centreon\ServiceProvider;
use Pimple\Container;
use Pimple\Psr11\ServiceLocator;
use Symfony\Component\Serializer;

trait DependenciesTrait
{
    /** @var ServiceLocator */
    protected $services;

    /**
     * List of dependencies
     *
     * @return array
     */
    public static function dependencies(): array
    {
        return [
            ServiceProvider::SERIALIZER,
        ];
    }

    /**
     * Extract services that are in use only
     *
     * @param Container $di
     */
    public function setDi(Container $di): void
    {
        $this->services = new ServiceLocator($di, static::dependencies());
    }

    /**
     * Get the Serializer service
     *
     * @return Serializer\Serializer
     */
    public function getSerializer(): Serializer\Serializer
    {
        return $this->services->get(ServiceProvider::SERIALIZER);
    }
}
