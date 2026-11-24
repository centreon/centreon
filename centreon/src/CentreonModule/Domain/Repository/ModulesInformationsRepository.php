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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Infrastructure\Doctrine\MigrationSetRegistry;
use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;
use Core\Common\Domain\Exception\RepositoryException;

class ModulesInformationsRepository extends ServiceEntityRepository
{
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
     * @throws RepositoryException
     *
     * @return list<string>|null class names without namespace, null when the module name cannot have migrations
     */
    public function findExecutedMigrations(string $module): ?array
    {
        if (! MigrationSet::isModuleName($module)) {
            return null;
        }

        $table = MigrationSetRegistry::table(MigrationSet::module($module));

        try {
            // Created by the first migration run. Checked first: a failing query is logged as an SQL error.
            $tableExists = $this->db->fetchOne(
                $this->db->createQueryBuilder()
                    ->select('1')
                    ->from('`information_schema`.`tables`')
                    ->where('`table_schema` = DATABASE()')
                    ->andWhere('`table_name` = :table')
                    ->getQuery(),
                QueryParameters::create([QueryParameter::string('table', $table)])
            );
            if ($tableExists === false) {
                return [];
            }

            $versions = $this->db->fetchFirstColumn(
                $this->db->createQueryBuilder()->select('`version`')->from("`{$table}`")->getQuery()
            );
        } catch (\Throwable $exception) {
            throw new RepositoryException('Cannot read the executed migrations of the module', ['module' => $module], $exception);
        }

        $migrations = [];
        foreach ($versions as $version) {
            if (is_string($version)) {
                $migrations[] = mb_substr((string) mb_strrchr('\\' . $version, '\\'), 1);
            }
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
