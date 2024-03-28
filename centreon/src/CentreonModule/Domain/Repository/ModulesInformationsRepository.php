<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;

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
