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

class Migration000019101900 extends AbstractCoreMigration implements LegacyMigrationInterface
{
    use LoggerTrait;

    private const VERSION = '19.10.19';

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


        /* Update-19.10.19.php */

        $centreonLog = new \CentreonLog();

        // error specific content
        $versionOfTheUpgrade = 'UPGRADE - 19.10.19 : ';
        $errorMessage = '';

        try {
            $statement = $pearDB->query(
                'SELECT COLUMN_DEFAULT
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = \'centreon\'
                AND TABLE_NAME = \'on_demand_macro_host\'
                AND COLUMN_NAME = \'is_password\''
            );
            if (($result = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
                $defaultValue = $result['COLUMN_DEFAULT'];
                if ($defaultValue !== 0) {
                    // An update is required
                    $errorMessage = 'Impossible to alter the table on_demand_macro_host';
                    $pearDB->query('ALTER TABLE on_demand_macro_host ALTER is_password SET DEFAULT 0');
                    $errorMessage = 'Impossible to update the column on_demand_macro_host.is_password';
                    $pearDB->query('UPDATE on_demand_macro_host SET is_password = 0 WHERE is_password IS NULL');
                }
            }
            $statement = $pearDB->query(
                'SELECT COLUMN_DEFAULT
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = \'centreon\'
                AND TABLE_NAME = \'on_demand_macro_service\'
                AND COLUMN_NAME = \'is_password\''
            );
            if (($defaultValue = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
                $defaultValue = $result['COLUMN_DEFAULT'];
                if ($defaultValue !== 0) {
                    // An update is required
                    $errorMessage = 'Impossible to alter the table on_demand_macro_service';
                    $pearDB->query('ALTER TABLE on_demand_macro_service ALTER is_password SET DEFAULT 0');
                    $errorMessage = 'Impossible to update the column on_demand_macro_service.is_password';
                    $pearDB->query('UPDATE on_demand_macro_service SET is_password = 0 WHERE is_password IS NULL');
                }
            }
        } catch (\Throwable $ex) {
            $centreonLog->insertLog(
                4,
                $versionOfTheUpgrade . $errorMessage .
                " - Code : " . $ex->getCode() .
                " - Error : " . $ex->getMessage() .
                " - Trace : " . $ex->getTraceAsString()
            );
            throw new \Exception($versionOfTheUpgrade . $errorMessage, $ex->getCode(), $ex);
        }
        // Contact language with transaction
        try {
            $pearDB->beginTransaction();
            $errorMessage = "Unable to Update user language";
            $pearDB->query(
                "UPDATE contact SET contact_lang = CONCAT(contact_lang, '.UTF-8')
                WHERE contact_lang NOT LIKE '%UTF-8' AND contact_lang <> 'browser' AND contact_lang <> ''"
            );
            $pearDB->commit();
        } catch (\Throwable $ex) {
            $pearDB->rollBack();
            $centreonLog->insertLog(
                4,
                $versionOfTheUpgrade . $errorMessage .
                " - Code : " . $ex->getCode() .
                " - Error : " . $ex->getMessage() .
                " - Trace : " . $ex->getTraceAsString()
            );
            throw new \Exception($versionOfTheUpgrade . $errorMessage, $ex->getCode(), $ex);
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
