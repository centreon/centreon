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
 * @class Config
 * @category Database
 * @package  Centreon
 * @author   qgarnier <qgarnier@centreon.com>
 * @license  GPLv2 http://www.gnu.org/licenses
 * @see     http://www.centreon.com
 */
class Config
{
    /** @var string */
    public $XMLFile;

    /** @var array */
    private $defaultConfiguration;

    /** @var array */
    public $tables = [];

    /** @var CentreonDB */
    public $centstorageDb;

    /** @var CentreonDB */
    private $centreonDb;

    /**
     * Config constructor
     *
     * @param CentreonDB $centstorageDb the centstorage database
     * @param string $file the xml file name
     * @param CentreonDB $centreonDb the centreon database
     *
     * @throws Exception
     */
    public function __construct($centstorageDb, $file, $centreonDb)
    {
        $this->XMLFile = $file;
        $this->centstorageDb = $centstorageDb;
        $this->centreonDb = $centreonDb;
        $this->loadCentreonDefaultConfiguration();
        $this->parseXML($this->XMLFile);
    }

    /**
     * @throws PDOException
     * @return void
     */
    public function loadCentreonDefaultConfiguration(): void
    {
        $queryOptions = 'SELECT `opt`.`key`, `opt`.`value` '
            . 'FROM `options` opt '
            . 'WHERE `opt`.`key` IN ('
            . "'partitioning_backup_directory', 'partitioning_backup_format', "
            . "'partitioning_retention', 'partitioning_retention_forward'"
            . ')';
        $res = $this->centreonDb->query($queryOptions);

        while ($row = $res->fetchRow()) {
            $this->defaultConfiguration[$row['key']] = $row['value'];
        }
    }

    /**
     * Parse XML configuration file to get properties of table to process
     *
     * @param string $xmlfile the xml file name
     *
     * @throws Exception
     * @return null
     */
    public function parseXML($xmlfile): void
    {
        if (! file_exists($xmlfile)) {
            throw new Exception("Config file '" . $xmlfile . "' does not exist\n");
        }
        $node = new SimpleXMLElement(file_get_contents($xmlfile));
        foreach ($node->table as $table_config) {
            $table = new MysqlTable(
                $this->centstorageDb,
                (string) $table_config['name'],
                (string) dbcstg
            );
            if (! is_null($table->getName()) && ! is_null($table->getSchema())) {
                $table->setActivate((string) $table_config->activate);
                $table->setColumn((string) $table_config->column);
                $table->setType((string) $table_config->type);
                $table->setDuration('daily');
                $table->setTimezone((string) $table_config->timezone);

                if (isset($this->defaultConfiguration['partitioning_retention'])) {
                    $table->setRetention((string) $this->defaultConfiguration['partitioning_retention']);
                } else {
                    $table->setRetention('365');
                }

                if (isset($this->defaultConfiguration['partitioning_retention_forward'])) {
                    $table->setRetentionForward((string) $this->defaultConfiguration['partitioning_retention_forward']);
                } else {
                    $table->setRetentionForward('10');
                }

                if (isset($this->defaultConfiguration['partitioning_backup_directory'])) {
                    $table->setBackupFolder((string) $this->defaultConfiguration['partitioning_backup_directory']);
                } else {
                    $table->setBackupFolder('/var/backups/');
                }

                $table->setBackupFormat('%Y-%m-%d');

                $table->setCreateStmt((string) $table_config->createstmt);
                $this->tables[$table->getName()] = $table;
            }
        }
    }

    /**
     * Return all tables partitioning properties
     *
     * @return array
     */
    public function getTables()
    {
        return $this->tables;
    }

    /**
     * Return partitioning properties for a specific table
     *
     * @param string $name the table name
     *
     * @return string
     */
    public function getTable($name)
    {
        foreach ($this->tables as $key => $instance) {
            if ($key == $name) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * Check if each table property is set
     *
     * @return bool
     */
    public function isValid()
    {
        foreach ($this->tables as $key => $inst) {
            if (! $inst->isValid()) {
                return false;
            }
        }

        return true;
    }
}
