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

class Migration000002020011 extends AbstractDsmMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '2.2.0';

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

        // 2.2.0/sql

        $pearDBStorage->query(
            <<<'SQL'
                DROP TABLE `mod_dsm_cache`
                SQL
        );
        $pearDBStorage->query(
            <<<'SQL'
                DROP TABLE `mod_dsm_locks`
                SQL
        );

        $pearDBStorage->query(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS `mod_dsm_cache` (
                `cache_id` int(11) NOT NULL AUTO_INCREMENT,
                `host_id` int(11) DEFAULT NULL,
                `ctime` int(11) DEFAULT NULL,
                `status` smallint(6) DEFAULT NULL,
                `pool_prefix` varchar(255) DEFAULT NULL,
                `id` varchar(1024) DEFAULT NULL,
                `macros` text,
                `output` text,
                PRIMARY KEY (`cache_id`),
                KEY `cache_mult_idx` (`host_id`,`id`,`cache_id`),
                KEY `pool_prefix` (`pool_prefix`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8
                SQL
        );
        $pearDBStorage->query(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS `mod_dsm_locks` (
                `lock_id` int(11) NOT NULL AUTO_INCREMENT,
                `host_id` int(11) DEFAULT NULL,
                `service_id` int(11) DEFAULT NULL,
                `status` int(11) DEFAULT NULL,
                `ctime` int(11) DEFAULT NULL,
                `internal_id` int(11) DEFAULT NULL,
                `id` varchar(1024) DEFAULT NULL,
                PRIMARY KEY (`lock_id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8
                SQL
        );
        $pearDBStorage->query(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS `mod_dsm_history` (
                `history_id` int(11) NOT NULL AUTO_INCREMENT,
                `host_id` int(11) DEFAULT NULL,
                `service_id` int(11) DEFAULT NULL,
                `ctime` int(11) DEFAULT NULL,
                `status` smallint(6) DEFAULT NULL,
                `internal_id` int(11) DEFAULT NULL,
                `id` varchar(1024) DEFAULT NULL,
                `macros` text,
                `output` text,
                PRIMARY KEY (`history_id`),
                KEY `ctime` (`ctime`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8
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
