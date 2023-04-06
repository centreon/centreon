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

require_once __DIR__ . '/../bootstrap.php';
require_once _CENTREON_ETC_ . '/centreon.conf.php';

$pearDB = new CentreonDB(CentreonDB::LABEL_DB_CONFIGURATION);
$pearDBO = new CentreonDB(CentreonDB::LABEL_DB_REALTIME);

convertCharset(db, $pearDB, 'security_token');
convertCharset(dbcstg, $pearDBO);

function convertCharset(string $dbName, CentreonDB $db, string ...$excluded_tables): void
{
    try {
        $errorMessage = '';

        // Get the list of tables in the database
        $query = 'SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = :database';
        $stmt = $db->prepare($query);
        $stmt->execute([':database' => $dbName]);
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Loop through the tables and convert them to utf8mb4
        foreach ($tables as $table) {
            $tableName = $table['table_name'];
            $tableType = $table['table_type'];

            // Skip the table if it is a view or an explicitly excluded table
            if ($tableType !== 'BASE TABLE' || in_array($tableName, $excluded_tables, true)) {
                continue;
            }

            $errorMessage = "Couldn't change charset for table: " . $tableName . "\n";
            // Create a query to alter the table and change the character set to utf8mb4
            $query = "ALTER TABLE `{$tableName}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $db->query($query);

            // Specific fields we must restore at their default values (utf8mb4_bin).
            if ('metrics' === $tableName) {
                $query = "ALTER TABLE `{$tableName}` MODIFY `metric_name` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL";
                $db->query($query);
            } elseif ('provider_configuration' === $tableName) {
                $query = "ALTER TABLE `{$tableName}` MODIFY `custom_configuration` JSON NOT NULL";
                $db->query($query);
            }
        }

        echo "All tables of {$dbName} had their charset converted.\n";
    } catch (\PDOException $e) {
        throw new \Exception($errorMessage, (int) $e->getCode(), $e);
    }
}
