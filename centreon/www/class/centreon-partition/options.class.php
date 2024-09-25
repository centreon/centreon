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
 * @class Options
 * @description Class that checks program options
 */
class Options
{
    const INFO = "info";
    const DEBUG = "debug";
    const WARNING = "warning";
    const ERROR = "error";

    /** @var array|false|false[]|string[] */
    public $options;
    /** @var string */
    public $shortopts;
    /** @var string */
    public $verbosity = "info";
    /** @var mixed */
    public $confFile;
    /** @var string */
    public $version = '1.1';

    /**
     * Options constructor
     */
    public function __construct()
    {
        $this->shortopts = "u:"; /** Update Table partition */
        $this->shortopts .= "o:"; /** Optimize */
        $this->shortopts .= "m:"; /** Migrate Partition */
        $this->shortopts .= "c:"; /** Create table with Partition */
        $this->shortopts .= "b:"; /** Backup Partitions */
        $this->shortopts .= "p:"; /** Purge Partitions */
        $this->shortopts .= "l:"; /** List all partitions from a table */
        $this->shortopts .= "s:"; /** Schema for table which table partitions will be listed */
        $this->shortopts .= "h"; /** Help */
        $this->options = getopt($this->shortopts);
        $this->updateVerboseLevel();
    }
    
    /**
     * get option value
     *
     * @param string $label
     *
     * @return false|mixed|string|null
     */
    public function getOptionValue($label)
    {
        $value = isset($this->options[$label]) ? $this->options[$label] : null;

        return $value;
    }
    
    /**
     * Check options and print help if necessary
     *
     * @return bool
     */
    public function isMissingOptions()
    {
        if (!isset($this->options) || count($this->options) == 0) {
            return(true);
        } elseif (isset($this->options["h"])) {
            return(true);
        } elseif (!isset($this->options["m"]) && !isset($this->options["u"]) &&
                !isset($this->options["c"]) && !isset($this->options["p"])
                && !isset($this->options["l"]) && !isset($this->options["b"]) &&
                !isset($this->options["o"])) {
            return(true);
        }

        return (false);
    }

    /**
     * Check if migration option is set
     *
     * @return bool
     */
    public function isMigration()
    {
        if (isset($this->options["m"]) && file_exists($this->options["m"])) {
            $this->confFile = $this->options["m"];
            return (true);
        }

        return(false);
    }
    
    /**
     * Check if partitions initialization option is set
     *
     * @return bool
     */
    public function isCreation()
    {
        if (isset($this->options["c"]) && file_exists($this->options["c"])) {
            $this->confFile = $this->options["c"];
            return (true);
        }

        return(false);
    }
    
    /**
     * Check if partitionned table update option is set
     *
     * @return bool
     */
    public function isUpdate()
    {
        if (isset($this->options["u"]) && file_exists($this->options["u"])) {
            $this->confFile = $this->options["u"];
            return (true);
        }

        return(false);
    }
    
    /**
     * Check if backup option is set
     *
     * @return bool
     */
    public function isBackup()
    {
        if (isset($this->options["b"]) && is_writable($this->options["b"])) {
            $this->confFile = $this->options["b"];
            return (true);
        }

        return(false);
    }
    
    /**
     * Check if optimize option is set
     *
     * @return bool
     */
    public function isOptimize()
    {
        if (isset($this->options["o"]) && is_writable($this->options["o"])) {
            $this->confFile = $this->options["o"];
            return (true);
        }

        return(false);
    }
    
    /**
     * Check if purge option is set
     *
     * @return bool
     */
    public function isPurge()
    {
        if (isset($this->options["p"]) && is_writable($this->options["p"])) {
            $this->confFile = $this->options["p"];
            return (true);
        }

        return(false);
    }
    
    /**
     * Check if parts list option is set
     *
     * @return bool
     */
    public function isPartList()
    {
        if (isset($this->options["l"]) && $this->options["l"] != ""
            && isset($this->options["s"]) && $this->options["s"] != "") {
            return (true);
        }

        return(false);
    }
    
    /**
     * Update verbose level of program
     *
     * @return void
     */
    private function updateVerboseLevel()
    {
        if (isset($this->options) && isset($this->options["v"])) {
            $this->verbosity = $verbosity;
        }
    }
    
    /**
     * returns verbose level of program
     *
     * @return mixed|string
     */
    public function getVerboseLevel()
    {
        return $this->verbosity;
    }
    
    /**
     * returns centreon partitioning $confFile
     *
     * @return mixed
     */
    public function getConfFile()
    {
        return $this->confFile;
    }
    
    /**
     * Print program usage
     *
     * @return void
     */
    public function printHelp()
    {
        echo "Version: $this->version\n";
        echo "Program options:\n";
        echo "    -h  print program usage\n";
        echo "Execution mode:\n";
        echo "    -c <configuration file>       create tables and create partitions\n";
        echo "    -m <configuration file>       migrate existing table to partitioned table\n";
        echo "    -u <configuration file>       update partitionned tables with new partitions\n";
        echo "    -o <configuration file>       optimize tables\n";
        echo "    -p <configuration file>       purge tables\n";
        echo "    -b <configuration file>       backup last part for each table\n";
        echo "    -l <table> -s <database name> List all partitions for a table.\n";
    }
}
