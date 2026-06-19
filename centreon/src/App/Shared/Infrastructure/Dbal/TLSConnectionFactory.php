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

namespace App\Shared\Infrastructure\Dbal;

use App\Shared\Infrastructure\Database\DatabaseTLSResolver;
use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * @phpstan-import-type Params from DriverManager
 */
#[AsDecorator('doctrine.dbal.connection_factory')]
class TLSConnectionFactory
{
    public function __construct(
        private readonly ConnectionFactory $inner,
    ) {
    }

    /**
     * @phpstan-param Params $params
     * @param array<string, string> $mappingTypes
     */
    public function createConnection(array $params, Configuration|null $config = null, EventManager|null $eventManager = null, array $mappingTypes = []): Connection
    {
        $tlsOptions = DatabaseTLSResolver::getTLSOptions();
        /** @var array<mixed> $driverOptions */
        $driverOptions = $params['driverOptions'] ?? [];
        foreach ($tlsOptions as $optionKey => $optionValue) {
            $driverOptions[$optionKey] = $optionValue;
        }
        $params['driverOptions'] = $driverOptions;

        /** @var Params $params */
        return $this->inner->createConnection($params, $config, $eventManager, $mappingTypes);
    }
}
