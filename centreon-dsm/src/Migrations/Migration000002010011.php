<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Migrations;

use Centreon\Domain\Log\LoggerTrait;
use CentreonDsm\Migration\Infrastructure\Repository\AbstractDsmMigration;
use Core\Migration\Application\Repository\LegacyMigrationInterface;
use Pimple\Container;

class Migration000002010011 extends AbstractDsmMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '2.1.0';

    public function __construct(
        private readonly Container $dependencyInjector,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return sprintf(_('Update to %s'), self::VERSION);
    }

    /**
     * {@inheritDoc}
     */
    public function up(): void
    {
        $pearDB = $this->dependencyInjector['configuration_db'];

        // 2.1.0/sql

        $pearDB->query(
            <<<'SQL'
                ALTER TABLE mod_dsm_pool
                MODIFY COLUMN `pool_prefix` varchar(255) DEFAULT NULL
                SQL
        );
    }

    /**
     * {@inheritDoc}
     */
    public function down(): void
    {
        // nothing
    }
}
