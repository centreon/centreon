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
 * @class Options
 * @description Class that checks program options
 */
class Options
{
    public const INFO = 'info';
    public const DEBUG = 'debug';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    /** @var array|false|false[]|string[] */
    public $options;

    /** @var string */
    public $shortopts;

    /** @var string */
    public $verbosity = 'info';

    /** @var mixed */
    public $confFile;

    /** @var string */
    public $version = '1.1';

    /**
     * Options constructor
     */
    public function __construct()
    {
        $this->shortopts .= 'o:'; /** Optimize */
        $this->shortopts .= 'm:'; /** Migrate Partition */
        $this->shortopts .= 'c:'; /** Create table with Partition */
        $this->shortopts .= 'b:'; /** Backup Partitions */
        $this->shortopts .= 'p:'; /** Purge Partitions */
        $this->shortopts .= 'l:'; /** List all partitions from a table */
        $this->shortopts .= 's:'; /** Schema for table which table partitions will be listed */
        $this->shortopts .= 'h'; /** Help */
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
        return $this->options[$label] ?? null;
    }

    /**
     * Check options and print help if necessary
     *
     * @return bool
     */
    public function isMissingOptions()
    {
        if (! isset($this->options) || count($this->options) == 0) {
            return true;
        }
        if (isset($this->options['h'])) {
            return true;
        }

        return (bool) (! isset($this->options['m']) && ! isset($this->options['u'])
                && ! isset($this->options['c']) && ! isset($this->options['p'])
                && ! isset($this->options['l']) && ! isset($this->options['b'])
                && ! isset($this->options['o']));
    }

    /**
     * Check if migration option is set
     *
     * @return bool
     */
    public function isMigration()
    {
        if (isset($this->options['m']) && file_exists($this->options['m'])) {
            $this->confFile = $this->options['m'];

            return true;
        }

        return false;
    }

    /**
     * Check if partitions initialization option is set
     *
     * @return bool
     */
    public function isCreation()
    {
        if (isset($this->options['c']) && file_exists($this->options['c'])) {
            $this->confFile = $this->options['c'];

            return true;
        }

        return false;
    }

    /**
     * Check if partitionned table update option is set
     *
     * @return bool
     */
    public function isUpdate()
    {
        if (isset($this->options['u']) && file_exists($this->options['u'])) {
            $this->confFile = $this->options['u'];

            return true;
        }

        return false;
    }

    /**
     * Check if backup option is set
     *
     * @return bool
     */
    public function isBackup()
    {
        if (isset($this->options['b']) && is_writable($this->options['b'])) {
            $this->confFile = $this->options['b'];

            return true;
        }

        return false;
    }

    /**
     * Check if optimize option is set
     *
     * @return bool
     */
    public function isOptimize()
    {
        if (isset($this->options['o']) && is_writable($this->options['o'])) {
            $this->confFile = $this->options['o'];

            return true;
        }

        return false;
    }

    /**
     * Check if purge option is set
     *
     * @return bool
     */
    public function isPurge()
    {
        if (isset($this->options['p']) && is_writable($this->options['p'])) {
            $this->confFile = $this->options['p'];

            return true;
        }

        return false;
    }

    /**
     * Check if parts list option is set
     *
     * @return bool
     */
    public function isPartList()
    {
        return (bool) (isset($this->options['l']) && $this->options['l'] != ''
            && isset($this->options['s']) && $this->options['s'] != '');
    }

    /**
     * Update verbose level of program
     *
     * @return void
     */
    private function updateVerboseLevel(): void
    {
        if (isset($this->options, $this->options['v'])) {
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
    public function printHelp(): void
    {
        echo "Version: {$this->version}\n";
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
