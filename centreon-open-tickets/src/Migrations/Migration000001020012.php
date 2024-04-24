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
use CentreonOpenTickets\Migration\Infrastructure\Repository\AbstractOpenTicketsMigration;
use Core\Migration\Application\Repository\LegacyMigrationInterface;
use Pimple\Container;

class Migration000001020012 extends AbstractOpenTicketsMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '1.2.0';

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
        $pearDBStorage = $this->dependencyInjector['realtime_db'];

        // 1.2.0/sql

        $pearDB->query(
            <<<'SQL'
                ALTER TABLE mod_open_tickets_form_clone
                MODIFY `value` TEXT
                SQL
        );

        $pearDBStorage->query(
            <<<'SQL'
                CREATE INDEX `mod_open_tickets_timestamp_idx`
                ON `mod_open_tickets` (`timestamp`)
                SQL
        );
        $pearDBStorage->query(
            <<<'SQL'
                CREATE INDEX `mod_open_tickets_ticket_value_idx`
                ON `mod_open_tickets` (`ticket_value`(768))
                SQL
        );

        $pearDBStorage->query(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS `mod_open_tickets_data` (
                    `ticket_id` int(11) NOT NULL,
                    `subject` VARCHAR(2048),
                    `data_type` enum('0', '1') NOT NULL DEFAULT '1',
                    `data` TEXT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
                SQL
        );

        $pearDBStorage->query(
            <<<'SQL'
                ALTER TABLE `mod_open_tickets_data`
                ADD CONSTRAINT `mod_open_tickets_data_fk_1` FOREIGN KEY (`ticket_id`) REFERENCES `mod_open_tickets` (`ticket_id`) ON DELETE CASCADE
                SQL
        );

        $pearDBStorage->query(
            <<<'SQL'
                CREATE TABLE IF NOT EXISTS `mod_open_tickets_link` (
                    `ticket_id` int(11) NOT NULL,
                    `host_id` int(11),
                    `service_id` int(11) DEFAULT NULL,
                    `host_state` int(11),
                    `service_state` int(11),
                    `hostname` VARCHAR(1024),
                    `service_description` VARCHAR(1024) DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
                SQL
        );

        $pearDBStorage->query(
            <<<'SQL'
                ALTER TABLE `mod_open_tickets_link`
                ADD CONSTRAINT `mod_open_tickets_link_fk_1` FOREIGN KEY (`ticket_id`) REFERENCES `mod_open_tickets` (`ticket_id`) ON DELETE CASCADE
                SQL
        );

        $pearDBStorage->query(
            <<<'SQL'
                CREATE INDEX `mod_open_tickets_link_hostservice_idx`
                ON `mod_open_tickets_link` (`host_id`, `service_id`)
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology` (`topology_id`, `topology_name`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, `topology_url`, `topology_url_opt`, `topology_popup`, `topology_modules`, `topology_show`, `topology_style_class`, `topology_style_id`, `topology_OnClick`, `readonly`)
                VALUES (NULL,'Ticket Logs', 203, 20320,30,30,'./modules/centreon-open-tickets/views/logs/index.php',NULL,'0','0','1',NULL,NULL,NULL,'1')
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                INSERT INTO `topology_JS` (`id_page`, `PathName_js`)
                VALUES ('20320', './modules/centreon-open-tickets/lib/commonFunc.js')
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
