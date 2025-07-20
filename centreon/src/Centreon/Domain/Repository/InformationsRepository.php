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

namespace Centreon\Domain\Repository;

use Centreon\Domain\Entity\Informations;
use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;
use PDO;

class InformationsRepository extends ServiceEntityRepository
{
    /**
     * Export options
     *
     * @return Informations[]
     */
    public function getAll(): array
    {
        $sql = 'SELECT * FROM informations';
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $stmt->setFetchMode(PDO::FETCH_CLASS, Informations::class);

        $result = [];

        while ($row = $stmt->fetch()) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Find one by given key
     * @param string $key
     * @return Informations
     */
    public function getOneByKey($key): ?Informations
    {
        $sql = 'SELECT * FROM informations WHERE `key` = :key LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':key', $key, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch();
        $informations = null;
        if ($result) {
            $informations = new Informations();
            $informations->setKey($result['key']);
            $informations->setValue($result['value']);
        }

        return $informations;
    }

    /**
     * Turn on or off remote flag in database
     * @param string $flag ('yes' or 'no')
     * @return void
     */
    public function toggleRemote(string $flag): void
    {
        $sql = "UPDATE `informations` SET `value`= :state WHERE `key` = 'isRemote'";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':state', $flag, PDO::PARAM_STR);
        $stmt->execute();

        $centralState = ($flag === 'yes') ? 'no' : 'yes';
        $sql = "UPDATE `informations` SET `value`= :state WHERE `key` = 'isCentral'";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':state', $centralState, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Authorize Master to make calls to remote for Tasks
     * @param string $ip
     * @return void
     */
    public function authorizeMaster(string $ip): void
    {
        $sql = "DELETE FROM `informations` WHERE `key` = 'authorizedMaster'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        // resolve the address down to IP
        $ipAddress = gethostbyname($ip);
        $sql = "INSERT INTO `informations` (`key`, `value`) VALUES ('authorizedMaster', :ip)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':ip', $ipAddress, PDO::PARAM_STR);
        $stmt->execute();
    }
}
