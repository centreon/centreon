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
 * @class CentreonConfigEngine
 */
class CentreonConfigEngine
{
    /** @var CentreonDB */
    protected $db;

    /**
     * CentreonConfigEngine constructor
     *
     * @param CentreonDB $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Insert one or multiple broker directives
     *
     * @param int $serverId | id of monitoring server
     * @param array $directives | event broker directives
     *
     * @throws PDOException
     * @return void
     */
    public function insertBrokerDirectives($serverId, $directives = []): void
    {
        $deleteStmt = $this->db->prepare(
            'DELETE FROM cfg_nagios_broker_module WHERE cfg_nagios_id = :serverId'
        );
        $deleteStmt->bindValue(':serverId', (int) $serverId, PDO::PARAM_INT);
        $deleteStmt->execute();

        $insertStmt = $this->db->prepare(
            'INSERT INTO cfg_nagios_broker_module (broker_module, cfg_nagios_id)
            VALUES (:brokerModule, :serverId)'
        );
        foreach ($directives as $value) {
            if ($value != '') {
                $insertStmt->bindValue(':brokerModule', $value, PDO::PARAM_STR);
                $insertStmt->bindValue(':serverId', (int) $serverId, PDO::PARAM_INT);
                $insertStmt->execute();
            }
        }
    }

    /**
     * Used by form only
     *
     * @param null $serverId
     *
     * @throws PDOException
     * @return array
     */
    public function getBrokerDirectives($serverId = null)
    {
        $arr = [];
        $i = 0;
        if (! isset($_REQUEST['in_broker']) && $serverId) {
            $stmt = $this->db->prepare(
                'SELECT broker_module FROM cfg_nagios_broker_module WHERE cfg_nagios_id = :serverId'
            );
            $stmt->bindValue(':serverId', (int) $serverId, PDO::PARAM_INT);
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $arr[$i]['in_broker_#index#'] = $row['broker_module'];
                $i++;
            }
        } elseif (isset($_REQUEST['in_broker'])) {
            foreach ($_REQUEST['in_broker'] as $val) {
                $arr[$i]['in_broker_#index#'] = $val;
                $i++;
            }
        }

        return $arr;
    }

    /**
     * @param $engineId
     *
     * @throws PDOException
     * @return mixed|null
     */
    public function getTimezone($engineId = null)
    {
        $timezone = null;

        if (is_null($engineId) || empty($engineId)) {
            return $timezone;
        }

        $stmt = $this->db->prepare(
            'SELECT timezone FROM ('
            . 'SELECT timezone_name as timezone '
            . 'FROM cfg_nagios, timezone '
            . 'WHERE nagios_id = :engineId '
            . 'AND use_timezone = timezone_id '
            . 'UNION '
            . 'SELECT timezone_name as timezone '
            . 'FROM options, timezone '
            . "WHERE options.key = 'gmt' "
            . 'AND options.value = timezone_id '
            . ') as t LIMIT 1'
        );
        $stmt->bindValue(':engineId', (int) $engineId, PDO::PARAM_INT);
        $stmt->execute();
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $timezone = $row['timezone'];
        }

        return $timezone;
    }
}
