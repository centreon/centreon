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
use Core\Migration\Application\Repository\LegacyMigrationInterface;
use Core\Migration\Infrastructure\Repository\AbstractCoreMigration;
use Pimple\Container;

class Migration000002081500 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '2.8.15';

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

        // Update-2.8.15.php

        $res = $pearDB->query(
            "SHOW INDEXES FROM `traps_group` WHERE key_name = 'PRIMARY'"
        );
        if ($res->numRows() <= 0) {
            $pearDB->query(
                'ALTER TABLE `traps_group_relation` '
                . 'DROP FOREIGN KEY `traps_group_relation_ibfk_2`'
            );

            $pearDB->query(
                'ALTER TABLE `traps_group` '
                . '  CHANGE COLUMN `traps_group_id` '
                . '  `traps_group_id` INT NOT NULL AUTO_INCREMENT'
            );

            $pearDB->query(
                'ALTER TABLE `traps_group` ADD PRIMARY KEY (`traps_group_id`)'
            );

            $pearDB->query(
                'ALTER TABLE `traps_group` '
                . '  DROP KEY `traps_group_id`'
            );

            $pearDB->query(
                'ALTER TABLE `traps_group_relation` '
                . 'ADD CONSTRAINT `traps_group_relation_ibfk_2` '
                . 'FOREIGN KEY (`traps_group_id`) REFERENCES `traps_group` (`traps_group_id`) ON DELETE CASCADE'
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function down(): void
    {
        // nothing
    }
}
