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

/**
 * Class
 *
 * @class CentreonUUID
 */
class CentreonUUID
{
    /** @var CentreonDB */
    private $db;

    /**
     * CentreonUUID constructor
     *
     * @param CentreonDB $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get Centreon UUID
     *
     * @return string
     */
    public function getUUID(): string
    {
        if ($uuid = $this->getUUIDFromDatabase()) {
            return $uuid;
        }

        return $this->generateUUID();
    }

    /**
     * Get Centreon UUID previously stored in database
     *
     * @throws PDOException
     * @return false|string
     */
    private function getUUIDFromDatabase(): bool|string
    {
        $query = 'SELECT value '
            . 'FROM informations '
            . "WHERE informations.key = 'uuid' ";
        $result = $this->db->query($query);

        if ($result !== false && $row = $result->fetch()) {
            /** @var array<string, null|bool|int|string> $row */
            return (string) $row['value'];
        }

        return false;
    }

    /**
     * Generate UUID v4
     *
     * @throws CentreonDbException
     * @return string
     */
    private function generateUUID()
    {
        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );

        $query = "INSERT INTO informations VALUES ('uuid', ?) ";
        $queryValues = [$uuid];
        $stmt = $this->db->prepare($query);
        $this->db->execute($stmt, $queryValues);

        return $uuid;
    }
}
