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

use Centreon\Infrastructure\CentreonLegacyDB\CentreonDBAdapter;
use Psr\Container\ContainerInterface;

/**
 * Compatibility with Doctrine
 */
class CentreonDBManagerService
{
    /** @var string */
    private $defaultManager = 'configuration_db';

    /** @var array<string,mixed> */
    private $manager;

    /**
     * Construct
     *
     * @param ContainerInterface $services
     */
    public function __construct(ContainerInterface $services)
    {
        $this->manager = [
            'configuration_db' => new CentreonDBAdapter($services->get('configuration_db'), $this),
            'realtime_db' => new CentreonDBAdapter($services->get('realtime_db'), $this),
        ];
    }

    public function getAdapter(string $alias): CentreonDBAdapter
    {
        return $this->manager[$alias] ?? null;
    }

    /**
     * Get default adapter with DB connection
     *
     * @return CentreonDBAdapter
     */
    public function getDefaultAdapter(): CentreonDBAdapter
    {
        return $this->manager[$this->defaultManager];
    }

    /**
     * @param mixed $repository
     */
    public function getRepository($repository): mixed
    {
        return $this->manager[$this->defaultManager]
            ->getRepository($repository);
    }
}
