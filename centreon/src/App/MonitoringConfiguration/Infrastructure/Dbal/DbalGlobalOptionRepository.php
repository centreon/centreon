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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Repository\GlobalOptionRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DbalGlobalOptionRepository implements GlobalOptionRepository
{
    public const TABLE_NAME = 'options';
    private const INHERITANCE_MODE_KEY = 'inheritance_mode';

    /**
     * The only value enabling additive inheritance, as legacy reads it (`AddHost` passes the raw
     * option to `NewHostFactory::create()`, which compares it to 1). A fresh install ships `'3'`,
     * so additive inheritance is off unless an administrator turned it on.
     */
    private const INHERITANCE_MODE_ADDITIVE = '1';

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
    ) {
    }

    public function isAdditiveInheritanceEnabled(): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('`value`')
            ->from(self::TABLE_NAME)
            ->where($qb->expr()->eq('`key`', $qb->createNamedParameter(self::INHERITANCE_MODE_KEY)))
            ->setMaxResults(1);

        $value = $qb->executeQuery()->fetchOne();

        // A missing row reads as disabled, mirroring legacy: OptionService returns no option and
        // AddHost falls back to 0, which is not the additive mode.
        return $value === self::INHERITANCE_MODE_ADDITIVE;
    }
}
