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


require_once(__DIR__ . '/../bootstrap.php');
$pearDB = new CentreonDB("centreon");
$pearDBO = new CentreonDB("centstorage");

convertCharset("centreon", $pearDB, "security_token");

convertCharset("centreon_storage", $pearDBO);


function convertCharset($dbName, $db, $excluded_table = "")
{

    try {

        $errorMessage = "";

        // Get the list of tables in the database
        $query = "SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = :database";
        $stmt = $db->prepare($query);
        $stmt->execute([":database" => $dbName]);
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);


        // Loop through the tables and convert them to utf8mb4
        foreach ($tables as $table) {
            $tableName = $table["table_name"];
            $tableType = $table["table_type"];

            // Check if the table is a view, and skip it if it is
            if ($tableName === $excluded_table || $tableType !== "BASE TABLE") {
                continue;
            }

            $errorMessage = "Couldn't change charset for table: " . $tableName . "\n";
            // Create a query to alter the table and change the character set to utf8mb4
            $query = 'ALTER TABLE `' . $tableName . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
            $stmt = $db->prepare($query);
            $stmt->execute();

        }

        echo "All tables of " . $dbName . " had their charset converted.\n";
    } catch (\PDOException $e) {

        throw new \Exception($errorMessage, (int)$e->getCode(), $e);
    }
}


