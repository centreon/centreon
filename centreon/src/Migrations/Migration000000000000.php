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
use Centreon\Infrastructure\DatabaseConnection;
use Core\Migration\Application\Repository\MigrationInterface;
use Core\Migration\Infrastructure\Repository\AbstractCoreMigration;

class Migration000000000000 extends AbstractCoreMigration implements MigrationInterface
{
    use LoggerTrait;

    public function __construct(private DatabaseConnection $db)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return _('Create migrations table');
    }

    /**
     * {@inheritDoc}
     */
    public function up(): void
    {
        try {
            $this->db->query(
                <<<'SQL'
                CREATE TABLE IF NOT EXISTS `migrations` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'auto incremented id',
                    `module_id` int(11) DEFAULT NULL COMMENT 'linked module id',
                    `name` varchar(255) NOT NULL COMMENT 'name of the migration',
                    `executed_at` int(11) NOT NULL COMMENT 'migration execution date (timestamp)',
                    PRIMARY KEY (`id`),
                    CONSTRAINT `migrations_module_id`
                        FOREIGN KEY (`module_id`)
                        REFERENCES `modules_informations` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT 'table to store executed migrations'
                SQL
            );
        } catch (\Exception $e) {
            $this->error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new \Exception('Unable to create migrations table');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function down(): void
    {
    }
}
