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

namespace CentreonModule\Domain\Repository;

use App\Upgrade\Domain\MigrationSet;
use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;

class ModulesInformationsRepository extends ServiceEntityRepository
{
    private const string TABLE_EXISTS_QUERY = 'SELECT 1 FROM `information_schema`.`tables` WHERE `table_schema` = DATABASE() AND `table_name` = :table';

    /**
     * Get an associative array of all modules vs versions.
     *
     * @return string[]
     */
    public function getAllModuleVsVersion(): array
    {
        $sql = 'SELECT `name` AS `id`, `mod_release` AS `version` FROM `modules_informations`';

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $result = [];

        while ($row = $stmt->fetch()) {
            $result[$row['id']] = $row['version'];
        }

        return $result;
    }

    /**
     * @return list<string>|null class names without namespace, null when the module name cannot have migrations
     */
    public function findExecutedMigrations(string $module): ?array
    {
        if (! MigrationSet::isModuleName($module)) {
            return null;
        }

        $table = MigrationSet::module($module)->table();

        // Created by the first migration run. Checked first: a failing query is logged as an SQL error.
        $stmt = $this->db->prepare(self::TABLE_EXISTS_QUERY);
        $stmt->bindValue(':table', $table, \PDO::PARAM_STR);
        $stmt->execute();
        if ($stmt->fetch() === false) {
            return [];
        }

        $stmt = $this->db->prepare("SELECT `version` FROM `{$table}`");
        $stmt->execute();

        $migrations = [];
        while ($row = $stmt->fetch()) {
            $migrations[] = substr(strrchr('\\' . $row['version'], '\\'), 1);
        }

        return $migrations;
    }

    /**
     * Get id by name.
     *
     * @param string $name
     *
     * @return int|null
     */
    public function findIdByName($name): ?int
    {
        $sql = 'SELECT `id` FROM `modules_informations` WHERE `name` = :name LIMIT 0, 1';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('name', $name);
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            return intval($row['id']);
        }

        return null;
    }
}
