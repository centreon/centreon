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

class Migration000002000011 extends AbstractDsmMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '2.0.0';

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
        $pearDBStorage = $this->dependencyInjector['realtime_db'];

        // 2.0.0b1/sql

        // Structure of table `mod_dsm_cache`
        $pearDBStorage->query(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS `mod_dsm_cache` (
                `cache_id` int(11) NOT NULL AUTO_INCREMENT,
                `entry_time` int(11) DEFAULT NULL,
                `host_name` varchar(255) DEFAULT NULL,
                `ctime` int(11) DEFAULT NULL,
                `status` smallint(6) DEFAULT NULL,
                `macros` text,
                `id` varchar(255) DEFAULT NULL,
                `output` text,
                `finished` int(11) NOT NULL DEFAULT '0',
                PRIMARY KEY (`cache_id`),
                KEY `host_name` (`host_name`,`entry_time`,`ctime`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=latin1
                SQL
        );

        // Structure of table `mod_dsm_locks`
        $pearDBStorage->query(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS `mod_dsm_locks` (
                `lock_id` int(11) NOT NULL AUTO_INCREMENT,
                `host_name` varchar(255) DEFAULT NULL,
                `service_description` varchar(255) DEFAULT NULL,
                `id` varchar(255) DEFAULT NULL,
                `ctime` int(11) DEFAULT NULL,
                PRIMARY KEY (`lock_id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=latin1
                SQL
        );

        // 2.0.0b2/sql
        $pearDBStorage->query(
            <<<'SQL'
                ALTER TABLE mod_dsm_cache
                ADD index finished (`finished`)
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
