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

class Migration000002081800 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;
    private const VERSION = '2.8.18';

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
        $pearDB = $this->dependencyInjector['configuration_db'];
        $pearDBO = $this->dependencyInjector['realtime_db'];

        // Update-2.8.18.php

        $query = 'SELECT count(*) AS number '
        . 'FROM INFORMATION_SCHEMA.STATISTICS '
        . "WHERE table_schema = '" . $this->storageDbName . "' "
        . "AND table_name = 'centreon_acl' "
        . "AND index_name='index1'";
        $res = $pearDBO->query($query);
        $data = $res->fetchRow();
        if ($data['number'] === 0) {
            $pearDBO->query('ALTER TABLE centreon_acl ADD INDEX `index2` (`host_id`,`service_id`,`group_id`)');
        }

        // Update-DB-2.8.18.sql

        // lua custom output for centreon-broker
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `cb_type_field_relation`
                ADD COLUMN `jshook_name` VARCHAR(255) DEFAULT NULL
                SQL
        );
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `cb_type_field_relation`
                ADD COLUMN `jshook_arguments` VARCHAR(255) DEFAULT NULL
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                INSERT INTO `cb_module` (`name`, `libname`, `loading_pos`, `is_activated`)
                VALUES ('Generic', 'lua.so', 40,1)
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                INSERT INTO `cb_type` (`type_name`, `type_shortname`, `cb_module_id`)
                VALUES ('Stream connector', 'lua', (SELECT `cb_module_id` FROM `cb_module` WHERE `libname` = 'lua.so'))
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                INSERT INTO `cb_fieldgroup` (`groupname`, `displayname`, `multiple`, `group_parent_id`)
                VALUES ('lua_parameter', 'lua parameter', 1, NULL)
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                INSERT INTO `cb_field` (`fieldname`, `displayname`, `description`, `fieldtype`, `external`, `cb_fieldgroup_id`)
                VALUES
                ('path', 'Path', 'Path of the lua script.', 'text', NULL, NULL),
                ('type', 'Type', 'Type of the metric.', 'select', NULL, (SELECT `cb_fieldgroup_id` FROM `cb_fieldgroup` WHERE `groupname` = 'lua_parameter')),
                ('name', 'Name', 'Name of the metric.', 'text', NULL, (SELECT `cb_fieldgroup_id` FROM `cb_fieldgroup` WHERE `groupname` = 'lua_parameter')),
                ('value', 'Value', 'Value of the metric.', 'text', NULL, (SELECT `cb_fieldgroup_id` FROM `cb_fieldgroup` WHERE `groupname` = 'lua_parameter'))
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                INSERT INTO `cb_type_field_relation` (`cb_type_id`, `cb_field_id`, `is_required`, `order_display`, `jshook_name`, `jshook_arguments`)
                VALUES (
                (SELECT `cb_type_id` FROM `cb_type` WHERE `type_shortname` = 'lua'),
                (SELECT `cb_field_id` FROM `cb_field` WHERE `description` = 'Path of the lua script.'),
                1, 1, NULL, NULL),
                ((SELECT `cb_type_id` FROM `cb_type` WHERE `type_shortname` = 'lua'),
                (SELECT `cb_field_id` FROM `cb_field` WHERE `description` = 'Category filter for flux in output'),
                0, 2, NULL, NULL),
                ((SELECT `cb_type_id` FROM `cb_type` WHERE `type_shortname` = 'lua'),
                (SELECT `cb_field_id` FROM `cb_field` WHERE `description` = 'Type of the metric.'),
                0, 5, 'luaArguments', '{"target": "lua_parameter__value_%d"}'),
                ((SELECT `cb_type_id` FROM `cb_type` WHERE `type_shortname` = 'lua'),
                (SELECT `cb_field_id` FROM `cb_field` WHERE `description` = 'Name of the metric.'),
                0, 4, NULL, NULL),
                ((SELECT `cb_type_id` FROM `cb_type` WHERE `type_shortname` = 'lua'),
                (SELECT `cb_field_id` FROM `cb_field` WHERE `description` = 'Value of the metric.'),
                0, 3, NULL, NULL)
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                INSERT INTO `cb_list` (`cb_list_id`, `cb_field_id`, `default_value`)
                VALUES (
                    (SELECT IFNULL(MAX(l.cb_list_id), 0) + 1 from cb_list l), (SELECT `cb_field_id` FROM `cb_field` WHERE `description` = 'Type of the metric.'), 'string'
                )
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                INSERT INTO `cb_list_values` (`cb_list_id`, `value_name`, `value_value`)
                VALUES
                ((SELECT `cb_list_id` FROM `cb_list` WHERE `cb_field_id` =
                (SELECT `cb_field_id` FROM `cb_field` WHERE `description` = 'Type of the metric.')), 'String', 'string'
                ),
                ((SELECT `cb_list_id` FROM `cb_list` WHERE `cb_field_id` =
                (SELECT `cb_field_id` FROM `cb_field` WHERE `description` = 'Type of the metric.')), 'Number', 'number'
                ),
                ((SELECT `cb_list_id` FROM `cb_list` WHERE `cb_field_id` =
                (SELECT `cb_field_id` FROM `cb_field` WHERE `description` = 'Type of the metric.')), 'Password', 'password'
                )
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                INSERT INTO `cb_tag_type_relation` (`cb_tag_id`, `cb_type_id`, `cb_type_uniq`)
                VALUES (1, (SELECT `cb_type_id` FROM `cb_type` WHERE `type_shortname` = 'lua'), 0)
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
