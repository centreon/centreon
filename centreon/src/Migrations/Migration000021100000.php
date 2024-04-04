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

require_once __DIR__  . '/../../www/class/centreonLog.class.php';

use Centreon\Domain\Log\LoggerTrait;
use Core\Migration\Application\Repository\LegacyMigrationInterface;
use Core\Migration\Infrastructure\Repository\AbstractCoreMigration;
use Pimple\Container;

class Migration000021100000 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;

    private const VERSION = '21.10.0';

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
        $pearDBO = $this->dependencyInjector['realtime_db'];


        /* Update-CSTG-21.10.0-beta.1.sql */

        $pearDBO->query(
            <<<'SQL'
                ALTER TABLE downtimes
                MODIFY COLUMN `entry_time` INT(11) UNSIGNED DEFAULT NULL,
                MODIFY COLUMN `deletion_time` INT(11) UNSIGNED DEFAULT NULL,
                MODIFY COLUMN `duration` INT(11) UNSIGNED DEFAULT NULL,
                MODIFY COLUMN `end_time` INT(11) UNSIGNED DEFAULT NULL,
                MODIFY COLUMN `start_time` INT(11) UNSIGNED DEFAULT NULL,
                MODIFY COLUMN `actual_start_time` INT(11) UNSIGNED DEFAULT NULL,
                MODIFY COLUMN `actual_end_time` INT(11) UNSIGNED DEFAULT NULL
                SQL
        );

        $pearDBO->query(
            <<<'SQL'
                CREATE INDEX `sg_name_idx` ON servicegroups(`name`)
                SQL
        );

        $pearDBO->query(
            <<<'SQL'
                CREATE INDEX `hg_name_idx` ON hostgroups(`name`)
                SQL
        );

        $pearDBO->query(
            <<<'SQL'
                CREATE INDEX `instances_name_idx` ON instances(`name`)
                SQL
        );


        /* Update-21.10.0-beta.1.php */

        $centreonLog = new \CentreonLog();

        //error specific content
        $versionOfTheUpgrade = 'UPGRADE - 21.10.0-beta.1: ';

        /**
         * Query with transaction
         */
        try {
            $pearDB->beginTransaction();

            //Purge all session.
            $errorMessage = 'Impossible to purge the table session';
            $pearDB->query("DELETE FROM `session`");

            // Add TLS hostname in config brocker for input/outputs IPV4
            $statement = $pearDB->query("SELECT cb_field_id from cb_field WHERE fieldname = 'tls_hostname'");
            if ($statement->fetchColumn() === false) {
                $errorMessage  = 'Unable to update cb_field';
                $pearDB->query("
                    INSERT INTO `cb_field` (
                        `cb_field_id`, `fieldname`,`displayname`,
                        `description`,
                        `fieldtype`, `external`
                    ) VALUES (
                        null, 'tls_hostname', 'TLS Host name',
                        'Expected TLS certificate common name (CN) - leave blank if unsure.',
                        'text', NULL
                    )
                ");

                $errorMessage  = 'Unable to update cb_type_field_relation';
                $fieldId = $pearDB->lastInsertId();
                $pearDB->query("
                    INSERT INTO `cb_type_field_relation` (`cb_type_id`, `cb_field_id`, `is_required`, `order_display`) VALUES
                    (3, " . $fieldId . ", 0, 5)
                ");
            }

            $pearDB->commit();

            $constraintStatement = $pearDB->query(
                "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE CONSTRAINT_NAME='session_ibfk_1'"
            );
            if (($constraint = $constraintStatement->fetch()) && (int) $constraint['count'] === 0) {
                $errorMessage = 'Impossible to add Delete Cascade constraint on the table session';
                $pearDB->query(
                    "ALTER TABLE `session` ADD CONSTRAINT `session_ibfk_1` FOREIGN KEY (`user_id`) " .
                    "REFERENCES `contact` (`contact_id`) ON DELETE CASCADE"
                );
            }
        } catch (\Exception $e) {
            if ($pearDB->inTransaction()) {
                $pearDB->rollBack();
            }
            $centreonLog->insertLog(
                4,
                $versionOfTheUpgrade . $errorMessage .
                " - Code : " . (int)$e->getCode() .
                " - Error : " . $e->getMessage() .
                " - Trace : " . $e->getTraceAsString()
            );
            throw new \Exception($versionOfTheUpgrade . $errorMessage, (int)$e->getCode(), $e);
        }


        /* Update-DB-21.10.0-beta.1.sql */

        $pearDB->query(
            <<<'SQL'
                DROP TABLE `ws_token`
                SQL
        );

        // Create authentication tables and insert local configuration
        $pearDB->query(
            <<<'SQL'
                CREATE TABLE `provider_configuration` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `type` varchar(255) NOT NULL,
                `name` varchar(255) NOT NULL,
                `is_active` BOOLEAN NOT NULL DEFAULT 1,
                `is_forced` BOOLEAN NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_name` (`name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                INSERT INTO `provider_configuration` (type, name, is_active, is_forced)
                VALUES ('local', 'local', true, true)
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                CREATE TABLE `security_token` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `token` varchar(255) NOT NULL,
                `creation_date` bigint UNSIGNED NOT NULL,
                `expiration_date` bigint UNSIGNED DEFAULT NULL,
                PRIMARY KEY (`id`),
                INDEX `token_index` (`token`),
                INDEX `expiration_index` (`expiration_date`),
                UNIQUE KEY `unique_token` (`token`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                CREATE TABLE `security_authentication_tokens` (
                `token` varchar(255) NOT NULL,
                `provider_token_id` int(11) DEFAULT NULL,
                `provider_token_refresh_id` int(11) DEFAULT NULL,
                `provider_configuration_id` int(11) NOT NULL,
                `user_id` int(11) NOT NULL,
                PRIMARY KEY (`token`),
                KEY `security_authentication_tokens_token_fk` (`token`),
                KEY `security_authentication_tokens_provider_token_id_fk` (`provider_token_id`),
                KEY `security_authentication_tokens_provider_token_refresh_id_fk` (`provider_token_refresh_id`),
                KEY `security_authentication_tokens_configuration_id_fk` (`provider_configuration_id`),
                KEY `security_authentication_tokens_user_id_fk` (`user_id`),
                CONSTRAINT `security_authentication_tokens_configuration_id_fk` FOREIGN KEY (`provider_configuration_id`)
                REFERENCES `provider_configuration` (`id`) ON DELETE CASCADE,
                CONSTRAINT `security_authentication_tokens_provider_token_id_fk` FOREIGN KEY (`provider_token_id`)
                REFERENCES `security_token` (`id`) ON DELETE CASCADE,
                CONSTRAINT `security_authentication_tokens_provider_token_refresh_id_fk` FOREIGN KEY (`provider_token_refresh_id`)
                REFERENCES `security_token` (`id`) ON DELETE SET NULL,
                CONSTRAINT `security_authentication_tokens_user_id_fk` FOREIGN KEY (`user_id`)
                REFERENCES `contact` (`contact_id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8
                SQL
        );

        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `session`
                MODIFY `last_reload` BIGINT UNSIGNED
                SQL
        );

        // Add one-click export button column to contact
        $pearDB->query(
            <<<'SQL'
                ALTER TABLE `contact`
                ADD COLUMN `enable_one_click_export` enum('0','1') DEFAULT '0'
                SQL
        );


        /* Update-21.10.0-rc.1.php */

        //error specific content
        $versionOfTheUpgrade = 'UPGRADE - 21.10.0-rc.1: ';

        /**
         * Query with transaction
         */
        try {
            $pearDB->beginTransaction();

            $errorMessage = 'Impossible to clean openid options';

            $defaultValues = [
                'openid_connect_enable' => '0',
                'openid_connect_mode' => '1',
                'openid_connect_trusted_clients' => '',
                'openid_connect_blacklist_clients' => '',
                'openid_connect_base_url' => '',
                'openid_connect_authorization_endpoint' => '',
                'openid_connect_token_endpoint' => '',
                'openid_connect_introspection_endpoint' => '',
                'openid_connect_userinfo_endpoint' => '',
                'openid_connect_end_session_endpoint' => '',
                'openid_connect_scope' => '',
                'openid_connect_login_claim' => '',
                'openid_connect_redirect_url' => '',
                'openid_connect_client_id' => '',
                'openid_connect_client_secret' => '',
                'openid_connect_client_basic_auth' => '0',
                'openid_connect_verify_peer' => '0',
            ];

            $result = $pearDB->query("SELECT * FROM `options` WHERE options.key LIKE 'openid%'");
            $generalOptions = [];
            while ($row = $result->fetch()) {
                $generalOptions[$row["key"]] = $row["value"];
            }

            foreach ($defaultValues as $defaultValueName => $defautValue) {
                if (!isset($generalOptions[$defaultValueName])) {
                    $statement = $pearDB->prepare('INSERT INTO `options` (`key`, `value`) VALUES (:option_key, :option_value)');
                    $statement->bindValue(':option_key', $defaultValueName, \PDO::PARAM_STR);
                    $statement->bindValue(':option_value', $defautValue, \PDO::PARAM_STR);
                    $statement->execute();
                }
            }

            /**
             * Retrieve user filters
             */
            $statement = $pearDB->query(
                "SELECT `id`, `criterias` FROM `user_filter` WHERE `page_name` = 'events-view'"
            );

            $fixedCriteriaFilters = [];

            /**
             * Sort filter criteria was not correctly added during the 21.04.0
             * upgrade. It should be an array and not an object
             */
            $errorMessage = "Cannot parse filter values in user_filter table.";
            while ($filter = $statement->fetch()) {
                $id = $filter['id'];
                $decodedCriterias = json_decode($filter['criterias'], true);
                foreach ($decodedCriterias as $criteriaKey => $criteria) {
                    if (
                        $criteria['name'] === 'sort'
                        && is_array($criteria['value'])
                        && count($criteria['value']) === 2
                        && $criteria['value'][0] === 'status_severity_code'
                        && !in_array($criteria['value'][1], ['asc', 'desc'])
                    ) {
                        $decodedCriterias[$criteriaKey]['value'][1] = 'desc';
                    }
                }

                $fixedCriteriaFilters[$id] = json_encode($decodedCriterias);
            }

            /**
             * UPDATE SQL request on filters
             */
            $errorMessage = "Unable to update filter sort values in user_filter table.";
            foreach ($fixedCriteriaFilters as $id => $criterias) {
                $statement = $pearDB->prepare(
                    "UPDATE `user_filter` SET `criterias` = :criterias WHERE `id` = :id"
                );
                $statement->bindValue(':id', (int) $id, \PDO::PARAM_INT);
                $statement->bindValue(':criterias', $criterias, \PDO::PARAM_STR);
                $statement->execute();
            }

            $pearDB->commit();
        } catch (\Exception $e) {
            $pearDB->rollBack();

            $centreonLog->insertLog(
                4,
                $versionOfTheUpgrade . $errorMessage .
                " - Code : " . (int)$e->getCode() .
                " - Error : " . $e->getMessage() .
                " - Trace : " . $e->getTraceAsString()
            );

            throw new \Exception($versionOfTheUpgrade . $errorMessage, (int)$e->getCode(), $e);
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
