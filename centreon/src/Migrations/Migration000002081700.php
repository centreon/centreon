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

class Migration000002081700 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '2.8.17';

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

        // Update-DB-2.8.17.sql

        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `extended_host_information`
                DROP FOREIGN KEY `extended_host_information_ibfk_3`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `extended_host_information`
                DROP COLUMN `ehi_vrml_image`
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                DELETE FROM topology_JS
                WHERE PathName_js LIKE '%aculous%'
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                UPDATE `cb_field`
                SET `fieldname` = 'negotiation', `displayname` = 'Enable negotiation',
                `description` = 'Enable negotiation option (use only for version of Centren Broker >= 2.5)'
                WHERE `fieldname` = 'negociation'
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                UPDATE `cfg_centreonbroker_info`
                SET `config_key` = 'negotiation'
                WHERE `config_key` = 'negociation'
                SQL
        );

        // Delete duplicate entries in custom_view_user_relation
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `custom_view_user_relation`
                DROP FOREIGN KEY `fk_custom_views_usergroup_id`,
                DROP FOREIGN KEY `fk_custom_views_user_id`,
                DROP FOREIGN KEY `fk_custom_view_user_id`,
                DROP INDEX `view_user_unique_index`
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER IGNORE TABLE `custom_view_user_relation`
                ADD UNIQUE INDEX `view_user_unique_index` (`custom_view_id`, `user_id`),
                ADD UNIQUE INDEX `view_usergroup_unique_index` (`custom_view_id`, `usergroup_id`)
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `custom_view_user_relation`
                ADD CONSTRAINT `fk_custom_views_usergroup_id`
                    FOREIGN KEY (`usergroup_id`)
                    REFERENCES `centreon`.`contactgroup` (`cg_id`)
                    ON DELETE CASCADE,
                ADD CONSTRAINT `fk_custom_views_user_id`
                    FOREIGN KEY (`user_id`)
                    REFERENCES `centreon`.`contact` (`contact_id`)
                    ON DELETE CASCADE,
                ADD CONSTRAINT `fk_custom_view_user_id`
                    FOREIGN KEY (`custom_view_id`)
                    REFERENCES `centreon`.`custom_views` (`custom_view_id`)
                    ON DELETE CASCADE
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
