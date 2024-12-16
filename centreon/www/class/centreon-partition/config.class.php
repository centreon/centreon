<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
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
 * @link     http://www.centreon.com
 */
class Config
{
    /** @var string */
    public $XMLFile;
    /** @var */
    public $XMLfile; // FIXME not used
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
     * @return void
     * @throws PDOException
     */
    public function loadCentreonDefaultConfiguration(): void
    {
        $queryOptions = 'SELECT `opt`.`key`, `opt`.`value` ' .
            'FROM `options` opt ' .
            'WHERE `opt`.`key` IN (' .
            "'partitioning_backup_directory', 'partitioning_backup_format', " .
            "'partitioning_retention', 'partitioning_retention_forward'" .
            ')';
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
     * @return null
     * @throws Exception
     */
    public function parseXML($xmlfile): void
    {
        if (!file_exists($xmlfile)) {
            throw new \Exception("Config file '" . $xmlfile . "' does not exist\n");
        }
        $node = new SimpleXMLElement(file_get_contents($xmlfile));
        foreach ($node->table as $table_config) {
            $table = new MysqlTable(
                $this->centstorageDb,
                (string) $table_config["name"],
                (string) dbcstg
            );
            if (!is_null($table->getName()) && !is_null($table->getSchema())) {
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
        return ($this->tables);
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
                return ($instance);
            }
        }

        return (null);
    }
    
    /**
     * Check if each table property is set
     *
     * @return bool
     */
    public function isValid()
    {
        foreach ($this->tables as $key => $inst) {
            if (!$inst->isValid()) {
                return (false);
            }
        }

        return (true);
    }
}
