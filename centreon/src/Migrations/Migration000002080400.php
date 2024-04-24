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

class Migration000002080400 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '2.8.4';

    public function __construct(
        private readonly Container $dependencyInjector,
        private readonly string $storageDbName
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
        $pearDBO = $this->dependencyInjector['realtime_db'];

        // Update-2.8.4.php

        // Update comments unique key
        $query = "SELECT count(*) AS number
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE table_name = 'hosts'
                AND table_schema = '" . $this->storageDbName . "'
                AND column_name = 'timezone'";
        $res = $pearDBO->query($query);
        $data = $res->fetchRow();
        if ($data['number'] === 0) {
            $pearDBO->query('ALTER TABLE services ADD INDEX last_hard_state_change (last_hard_state_change)');
            $pearDBO->query('ALTER TABLE `hosts` ADD COLUMN `timezone` varchar(64) DEFAULT NULL AFTER `statusmap_image`');
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
