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

require_once __DIR__ . '/centreonUser.class.php';
require_once __DIR__ . '/centreonGMT.class.php';
require_once __DIR__ . '/centreonLogAction.class.php';
require_once __DIR__ . '/centreonExternalCommand.class.php';
require_once __DIR__ . '/centreonBroker.class.php';
require_once __DIR__ . '/centreonHostgroups.class.php';
require_once __DIR__ . '/centreonDBInstance.class.php';

/**
 * Class
 *
 * @class Centreon
 * @description Class for load application Centreon
 */
class Centreon
{
    /** @var */
    public $Nagioscfg;

    /** @var */
    public $optGen;

    /** @var */
    public $informations;

    /** @var */
    public $redirectTo;

    /** @var */
    public $modules;

    /** @var */
    public $hooks;

    // @var array : saved user's pagination filter value
    /** @var */
    public $historyPage;

    // @var string : saved last page's file name
    /** @var */
    public $historyLastUrl;

    // @var array : saved user's filters
    /** @var */
    public $historySearch;

    /** @var */
    public $historySearchService;

    /** @var */
    public $historySearchOutput;

    /** @var */
    public $historyLimit;

    /** @var */
    public $search_type_service;

    /** @var */
    public $search_type_host;

    /** @var int */
    public $poller = 0;

    /** @var */
    public $template;

    /** @var */
    public $hostgroup;

    /** @var */
    public $host_id;

    /** @var */
    public $host_group_search;

    /** @var */
    public $host_list_search;

    /** @var CentreonUser */
    public $user;

    /** @var CentreonGMT */
    public $CentreonGMT;

    /** @var CentreonLogAction */
    public $CentreonLogAction;

    /** @var CentreonExternalCommand */
    public $extCmd;

    /**
     * Centreon constructor
     *
     * @param array $userInfos User objects
     *
     * @throws PDOException
     */
    public function __construct($userInfos)
    {
        global $pearDB;

        // Get User informations
        $this->user = new CentreonUser($userInfos);

        // Get Local nagios.cfg file
        $this->initNagiosCFG();

        // Get general options
        $this->initOptGen();

        // Get general informations
        $this->initInformations();

        // Grab Modules
        $this->creatModuleList();

        // Grab Hooks
        $this->initHooks();

        // Create GMT object
        $this->CentreonGMT = new CentreonGMT($pearDB);

        // Create LogAction object
        $this->CentreonLogAction = new CentreonLogAction($this->user);

        // Init External CMD object
        $this->extCmd = new CentreonExternalCommand();
    }

    /**
     * Create a list of all module installed into Centreon
     *
     * @throws PDOException
     */
    public function creatModuleList(): void
    {
        $this->modules = [];
        $query = 'SELECT `name` FROM `modules_informations`';
        $dbResult = CentreonDBInstance::getDbCentreonInstance()->query($query);
        while ($result = $dbResult->fetch()) {
            $this->modules[$result['name']] = ['name' => $result['name'], 'gen' => false, 'license' => false];

            if (is_dir('./modules/' . $result['name'] . '/generate_files/')) {
                $this->modules[$result['name']]['gen'] = true;
            }
        }
        $dbResult = null;
    }

    /**
     * @return void
     */
    public function initHooks(): void
    {
        $this->hooks = [];

        foreach ($this->modules as $name => $parameters) {
            $hookPaths = glob(_CENTREON_PATH_ . '/www/modules/' . $name . '/hooks/*.class.php');
            foreach ($hookPaths as $hookPath) {
                if (preg_match('/\/([^\/]+?)\.class\.php$/', $hookPath, $matches)) {
                    require_once $hookPath;
                    $explodedClassName = explode('_', $matches[1]);
                    $className = '';
                    foreach ($explodedClassName as $partClassName) {
                        $className .= ucfirst(strtolower($partClassName));
                    }
                    if (class_exists($className)) {
                        $hookName = '';
                        $counter = count($explodedClassName);
                        for ($i = 1; $i < $counter; $i++) {
                            $hookName .= ucfirst(strtolower($explodedClassName[$i]));
                        }
                        $hookMethods = get_class_methods($className);
                        foreach ($hookMethods as $hookMethod) {
                            $this->hooks[$hookName][$hookMethod][] = ['path' => $hookPath, 'class' => $className];
                        }
                    }
                }
            }
        }
    }

    /**
     * Create history list
     */
    public function createHistory(): void
    {
        $this->historyPage = [];
        $this->historyLastUrl = '';
        $this->historySearch = [];
        $this->historySearchService = [];
        $this->historySearchOutput = [];
        $this->historyLimit = [];
        $this->search_type_service = 1;
        $this->search_type_host = 1;
    }

    /**
     * Initiate nagios option list
     *
     * @throws PDOException
     */
    public function initNagiosCFG(): void
    {
        $this->Nagioscfg = [];
        /*
         * We don't check activate because we can a server without a engine on localhost running
         * (but we order to get if we have one)
         */
        $DBRESULT = CentreonDBInstance::getDbCentreonInstance()->query(
            "SELECT illegal_object_name_chars, cfg_dir FROM cfg_nagios, nagios_server
            WHERE nagios_server.id = cfg_nagios.nagios_server_id
            AND nagios_server.localhost = '1'
            ORDER BY cfg_nagios.nagios_activate
            DESC LIMIT 1"
        );
        $this->Nagioscfg = $DBRESULT->fetch();
        $DBRESULT = null;
    }

    /**
     * Initiate general option list
     *
     * @throws PDOException
     */
    public function initOptGen(): void
    {
        $this->optGen = [];
        $DBRESULT = CentreonDBInstance::getDbCentreonInstance()->query('SELECT * FROM `options`');
        while ($opt = $DBRESULT->fetch()) {
            $this->optGen[$opt['key']] = $opt['value'];
        }
        $DBRESULT = null;
        unset($opt);
    }

    /**
     * Store centreon informations in session
     *
     * @throws PDOException
     * @return void
     */
    public function initInformations(): void
    {
        $this->informations = [];
        $result = CentreonDBInstance::getDbCentreonInstance()->query('SELECT * FROM `informations`');
        while ($row = $result->fetch()) {
            $this->informations[$row['key']] = $row['value'];
        }
    }

    /**
     * Check illegal char defined into nagios.cfg file
     *
     * @param string $name The string to sanitize
     *
     * @throws PDOException
     * @return string The string sanitized
     */
    public function checkIllegalChar($name)
    {
        $DBRESULT = CentreonDBInstance::getDbCentreonInstance()->query('SELECT illegal_object_name_chars FROM cfg_nagios');
        while ($data = $DBRESULT->fetchColumn()) {
            $name = str_replace(str_split($data), '', $name);
        }
        $DBRESULT = null;

        return $name;
    }
}
