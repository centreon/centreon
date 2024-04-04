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

class Migration000021040700 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;

    private const VERSION = '21.04.7';

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

        /* Update-21.04.7.php */

        $centreonLog = new \CentreonLog();

        //error specific content
        $versionOfTheUpgrade = 'UPGRADE - 21.04.7: ';

        /**
         * Query with transaction
         */
        try {
            $pearDB->beginTransaction();

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

            if ($pearDB->inTransaction()) {
                $pearDB->commit();
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
    }

    /**
     * {@inheritDoc}
     */
    public function down(): void
    {
        // nothing
    }
}
