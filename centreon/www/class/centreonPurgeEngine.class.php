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

require_once realpath(__DIR__ . '/../../config/centreon.config.php');
require_once realpath(__DIR__ . '/centreonDB.class.php');

/**
 * Class
 *
 * @class CentreonPurgeEngine
 * @description Class that handles MySQL table partitions
 */
class CentreonPurgeEngine
{
    /** @var string */
    public $purgeAuditLogQuery = 'DELETE FROM log_action WHERE action_log_date < __RETENTION__';

    /** @var CentreonDB */
    private $dbCentstorage;

    /** @var string */
    private $purgeCommentsQuery;

    /** @var string */
    private $purgeDowntimesQuery;

    /** @var array[] */
    private $tablesToPurge;

    /**
     * CentreonPurgeEngine constructor
     *
     * @throws Exception
     */
    public function __construct()
    {
        $this->purgeCommentsQuery = 'DELETE FROM comments WHERE (deletion_time is not null and deletion_time '
            . '< __RETENTION__) OR (expire_time < __RETENTION__ AND expire_time <> 0)';
        $this->purgeDowntimesQuery = 'DELETE FROM downtimes WHERE (actual_end_time is not null and actual_end_time '
            . '< __RETENTION__) OR (deletion_time is not null and deletion_time < __RETENTION__)';

        $this->tablesToPurge = ['data_bin' => ['retention_field' => 'len_storage_mysql', 'retention' => 0, 'is_partitioned' => false, 'ctime_field' => 'ctime'], 'logs' => ['retention_field' => 'archive_retention', 'retention' => 0, 'is_partitioned' => false, 'ctime_field' => 'ctime'], 'log_archive_host' => ['retention_field' => 'reporting_retention', 'retention' => 0, 'is_partitioned' => false, 'ctime_field' => 'date_end'], 'log_archive_service' => ['retention_field' => 'reporting_retention', 'retention' => 0, 'is_partitioned' => false, 'ctime_field' => 'date_end'], 'comments' => ['retention_field' => 'len_storage_comments', 'retention' => 0, 'is_partitioned' => false, 'custom_query' => $this->purgeCommentsQuery], 'downtimes' => ['retention_field' => 'len_storage_downtimes', 'retention' => 0, 'is_partitioned' => false, 'custom_query' => $this->purgeDowntimesQuery], 'log_action' => ['retention_field' => 'audit_log_retention', 'retention' => 0, 'is_partitioned' => false, 'custom_query' => $this->purgeAuditLogQuery]];

        $this->dbCentstorage = new CentreonDB('centstorage');

        $this->readConfig();

        $this->checkTablesPartitioned();
    }

    /**
     * @throws Exception
     * @return void
     */
    private function readConfig(): void
    {
        $query = 'SELECT len_storage_mysql,archive_retention,reporting_retention, '
            . 'len_storage_downtimes, len_storage_comments, audit_log_retention FROM config';
        try {
            $DBRESULT = $this->dbCentstorage->query($query);
        } catch (PDOException $e) {
            throw new Exception('Cannot get retention information');
        }

        $ltime = localtime();
        $row = $DBRESULT->fetchRow();
        foreach ($this->tablesToPurge as &$table) {
            if (isset($row[$table['retention_field']])
                && ! is_null($row[$table['retention_field']])
                && $row[$table['retention_field']] > 0
            ) {
                $table['retention'] = mktime(
                    0,
                    0,
                    0,
                    $ltime[4] + 1,
                    $ltime[3] - $row[$table['retention_field']],
                    $ltime[5] + 1900
                );
            }
        }
    }

    /**
     * @throws Exception
     * @return void
     */
    private function checkTablesPartitioned(): void
    {
        foreach ($this->tablesToPurge as $name => $value) {
            try {
                $DBRESULT = $this->dbCentstorage->query('SHOW CREATE TABLE `' . dbcstg . '`.`' . $name . '`');
            } catch (PDOException $e) {
                throw new Exception('Cannot get partition information');
            }

            $row = $DBRESULT->fetchRow();
            $matches = [];
            // dont care of MAXVALUE
            if (preg_match_all('/PARTITION `{0,1}(.*?)`{0,1} VALUES LESS THAN \((.*?)\)/', $row['Create Table'], $matches)) {
                $this->tablesToPurge[$name]['is_partitioned'] = true;
                $this->tablesToPurge[$name]['partitions'] = [];
                for ($i = 0; isset($matches[1][$i]); $i++) {
                    $this->tablesToPurge[$name]['partitions'][$matches[1][$i]] = $matches[2][$i];
                }
            }
        }
    }

    /**
     * @throws Exception
     * @return void
     */
    public function purge(): void
    {
        foreach ($this->tablesToPurge as $table => $parameters) {
            if ($parameters['retention'] > 0) {
                echo '[' . date(DATE_RFC822) . '] Purging table ' . $table . "...\n";
                if ($parameters['is_partitioned']) {
                    $this->purgeParts($table);
                } else {
                    $this->purgeOldData($table);
                }
                echo '[' . date(DATE_RFC822) . '] Table ' . $table . " purged\n";
            }
        }

        echo '[' . date(DATE_RFC822) . "] Purging index_data...\n";
        $this->purgeIndexData();
        echo '[' . date(DATE_RFC822) . "] index_data purged\n";

        echo '[' . date(DATE_RFC822) . "] Purging log_action_modification...\n";
        $this->purgeLogActionModification();
        echo '[' . date(DATE_RFC822) . "] log_action_modification purged\n";
    }

    /**
     * Drop partitions that are older than the retention duration
     * @param MysqlTable $table
     */
    private function purgeParts($table): int
    {
        $dropPartitions = [];
        foreach ($this->tablesToPurge[$table]['partitions'] as $partName => $partTimestamp) {
            if ($partTimestamp < $this->tablesToPurge[$table]['retention']) {
                $dropPartitions[] = '`' . $partName . '`';
                echo '[' . date(DATE_RFC822) . '] Partition will be delete ' . $partName . "\n";
            }
        }

        if (count($dropPartitions) <= 0) {
            return 0;
        }

        $request = 'ALTER TABLE `' . $table . '` DROP PARTITION ' . implode(', ', $dropPartitions);
        try {
            $DBRESULT = $this->dbCentstorage->query($request);
        } catch (PDOException $e) {
            throw new Exception('Error : Cannot drop partitions of table '
                . $table . ', ' . $e->getMessage() . "\n");

            return 1;
        }

        echo '[' . date(DATE_RFC822) . "] Partitions deleted\n";

        return 0;
    }

    /**
     * @param $table
     *
     * @throws Exception
     * @return void
     */
    private function purgeOldData($table): void
    {
        if (isset($this->tablesToPurge[$table]['custom_query'])) {
            $request = str_replace(
                '__RETENTION__',
                $this->tablesToPurge[$table]['retention'],
                $this->tablesToPurge[$table]['custom_query']
            );
        } else {
            $request = 'DELETE FROM ' . $table . ' ';
            $request .= 'WHERE ' . $this->tablesToPurge[$table]['ctime_field'] . ' < '
                . $this->tablesToPurge[$table]['retention'];
        }

        try {
            $DBRESULT = $this->dbCentstorage->query($request);
        } catch (PDOException $e) {
            throw new Exception('Error : Cannot purge ' . $table . ', ' . $e->getMessage() . "\n");
        }
    }

    /**
     * @throws Exception
     * @return void
     */
    private function purgeIndexData(): void
    {
        $request = "UPDATE index_data SET to_delete = '1' WHERE ";
        $request .= 'NOT EXISTS(SELECT 1 FROM ' . db . '.service WHERE service.service_id = index_data.service_id)';

        try {
            $DBRESULT = $this->dbCentstorage->query($request);
        } catch (PDOException $e) {
            throw new Exception('Error : Cannot purge index_data, ' . $e->getMessage() . "\n");
        }
    }

    /**
     * @throws Exception
     * @return void
     */
    private function purgeLogActionModification(): void
    {
        $request = 'DELETE FROM log_action_modification WHERE action_log_id '
            . 'NOT IN (SELECT action_log_id FROM log_action)';

        try {
            $DBRESULT = $this->dbCentstorage->query($request);
        } catch (PDOException $e) {
            throw new Exception('Error : Cannot purge log_action_modification, ' . $e->getMessage() . "\n");
        }
    }
}
