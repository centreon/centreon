<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State;

use Doctrine\DBAL\Connection;

/**
 * Makes host templates come from a paid Monitoring Connector. The test database has no plugin pack
 * manager: the module is declared installed and its tables are created as temporary tables, which
 * leave the test transaction open. A test using it calls {@see dropMonitoringConnectorTables()} in
 * its tearDown().
 */
trait InsertsMonitoringConnectorTemplates
{
    private function insertMonitoringConnectorTemplates(Connection $connection, int ...$templateIds): void
    {
        if ($connection->fetchOne("SELECT 1 FROM modules_informations WHERE name = 'centreon-pp-manager'") === false) {
            $connection->insert('modules_informations', ['name' => 'centreon-pp-manager']);
        }
        $connection->executeStatement(
            'CREATE TEMPORARY TABLE IF NOT EXISTS mod_ppm_pluginpack (pluginpack_id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(255) NOT NULL)'
        );
        $connection->executeStatement(
            'CREATE TEMPORARY TABLE IF NOT EXISTS mod_ppm_pluginpack_host (pluginpack_id INT NOT NULL, host_id INT NOT NULL)'
        );

        foreach ($templateIds as $templateId) {
            $connection->insert('mod_ppm_pluginpack', ['slug' => 'paid-connector-' . bin2hex(random_bytes(8))]);
            $connection->insert('mod_ppm_pluginpack_host', [
                'pluginpack_id' => (int) $connection->lastInsertId(),
                'host_id' => $templateId,
            ]);
        }
    }

    private function dropMonitoringConnectorTables(Connection $connection): void
    {
        $connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS mod_ppm_pluginpack_host');
        $connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS mod_ppm_pluginpack');
    }
}
