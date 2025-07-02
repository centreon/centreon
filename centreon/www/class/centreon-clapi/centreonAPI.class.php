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

namespace CentreonClapi;

use CentreonAuth;
use CentreonAuthLDAP;
use CentreonDB;
use CentreonLog;
use CentreonUserLog;
use CentreonXML;
use DateTime;
use Exception;
use HtmlAnalyzer;
use LogicException;
use PDO;
use PDOException;
use Pimple\Container;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

require_once _CENTREON_PATH_ . 'www/class/centreon-clapi/centreonExported.class.php';
require_once realpath(__DIR__ . '/../centreonDB.class.php');
require_once realpath(__DIR__ . '/../centreonXML.class.php');
require_once _CENTREON_PATH_ . 'www/include/configuration/configGenerate/DB-Func.php';
require_once _CENTREON_PATH_ . 'www/class/config-generate/generate.class.php';
require_once __DIR__ . '/../centreonAuth.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonAuth.LDAP.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonLog.class.php';
require_once realpath(__DIR__ . '/../centreonSession.class.php');

// General Centreon Management
require_once 'centreon.Config.Poller.class.php';

/**
 * Class
 *
 * @class CentreonAPI
 * @package CentreonClapi
 */
class CentreonAPI
{
    /** @var null */
    private static $instance = null;

    /** @var int */
    public $dateStart;

    /** @var string */
    public $login;

    /** @var string */
    public $password;

    /** @var string */
    public $action;

    /** @var string */
    public $object;

    /** @var array */
    public $options;

    /** @var CentreonDB */
    public $DB;

    /** @var CentreonDB */
    public $DBC;

    /** @var string */
    public $format;

    /** @var CentreonXML */
    public $xmlObj;

    /** @var int */
    public $debug = 0;

    /** @var mixed|string */
    public $variables;

    /** @var string */
    public $centreon_path;

    /** @var int */
    private $return_code = 0;

    /** @var Container */
    private $dependencyInjector;

    /** @var array */
    private $relationObject;

    /** @var array */
    private $objectTable;

    /** @var array */
    private $aExport = [];

    /** @var string */
    public $delim = ';';

    /**
     * CentreonAPI constructor
     *
     * @param string $user
     * @param string $password
     * @param string $action
     * @param string $centreon_path
     * @param array $options
     * @param Container $dependencyInjector
     *
     * @throws PDOException
     */
    public function __construct(
        $user,
        $password,
        $action,
        $centreon_path,
        $options,
        Container $dependencyInjector
    ) {
        $this->dependencyInjector = $dependencyInjector;
        if (isset($user)) {
            $this->login = htmlentities($user, ENT_QUOTES);
        }
        if (isset($password)) {
            $this->password = HtmlAnalyzer::sanitizeAndRemoveTags($password);
        }
        if (isset($action)) {
            $this->action = htmlentities(strtoupper($action), ENT_QUOTES);
        }

        $this->options = $options;
        $this->centreon_path = $centreon_path;

        $this->variables = $options['v'] ?? '';

        $this->object = isset($options['o']) ? htmlentities(strtoupper($options['o']), ENT_QUOTES) : '';

        $this->objectTable = [];

        /**
         * Centreon DB Connexion
         */
        $this->DB = $this->dependencyInjector['configuration_db'];
        $this->DBC = $this->dependencyInjector['realtime_db'];
        $this->dateStart = time();

        $this->relationObject = [];
        $this->relationObject['CMD'] = [
            'module' => 'core',
            'class' => 'Command',
            'export' => true,
        ];
        $this->relationObject['HOST'] = [
            'module' => 'core',
            'class' => 'Host',
            'libs' => [
                'centreonService.class.php',
                'centreonHostGroup.class.php',
                'centreonContact.class.php',
                'centreonContactGroup.class.php',
            ],
            'export' => true,
        ];
        $this->relationObject['SERVICE'] = [
            'module' => 'core',
            'class' => 'Service',
            'libs' => [
                'centreonHost.class.php',
            ],
            'export' => true,
        ];
        $this->relationObject['HGSERVICE'] = [
            'module' => 'core',
            'class' => 'HostGroupService',
            'export' => true,
        ];
        $this->relationObject['VENDOR'] = [
            'module' => 'core',
            'class' => 'Manufacturer',
            'export' => true,
        ];
        $this->relationObject['TRAP'] = [
            'module' => 'core',
            'class' => 'Trap',
            'export' => true,
        ];
        $this->relationObject['HG'] = [
            'module' => 'core',
            'class' => 'HostGroup',
            'export' => true,
        ];
        $this->relationObject['HC'] = [
            'module' => 'core',
            'class' => 'HostCategory',
            'export' => true,
        ];
        $this->relationObject['SG'] = [
            'module' => 'core',
            'class' => 'ServiceGroup',
            'export' => true,
        ];
        $this->relationObject['SC'] = [
            'module' => 'core',
            'class' => 'ServiceCategory',
            'export' => true,
        ];
        $this->relationObject['CONTACT'] = [
            'module' => 'core',
            'class' => 'Contact',
            'libs' => [
                'centreonCommand.class.php',
            ],
            'export' => true,
        ];
        $this->relationObject['LDAPCONTACT'] = [
            'module' => 'core',
            'class' => 'LDAPContactRelation',
            'export' => true,
        ];
        $this->relationObject['LDAP'] = [
            'module' => 'core',
            'class' => 'LDAP',
            'export' => true,
        ];
        $this->relationObject['CONTACTTPL'] = [
            'module' => 'core',
            'class' => 'ContactTemplate',
            'export' => true,
        ];
        $this->relationObject['CG'] = [
            'module' => 'core',
            'class' => 'ContactGroup',
            'export' => true,
        ];
        // Dependencies
        $this->relationObject['DEP'] = [
            'module' => 'core',
            'class' => 'Dependency',
            'export' => true,
        ];
        // Downtimes
        $this->relationObject['DOWNTIME'] = [
            'module' => 'core',
            'class' => 'Downtime',
            'export' => true,
        ];

        // RtDowntimes
        $this->relationObject['RTDOWNTIME'] = [
            'module' => 'core',
            'class' => 'RtDowntime',
            'export' => false,
        ];

        // RtAcknowledgement
        $this->relationObject['RTACKNOWLEDGEMENT'] = [
            'module' => 'core',
            'class' => 'RtAcknowledgement',
            'export' => false,
        ];

        // Templates
        $this->relationObject['HTPL'] = [
            'module' => 'core',
            'class' => 'HostTemplate',
            'export' => true,
        ];
        $this->relationObject['STPL'] = [
            'module' => 'core',
            'class' => 'ServiceTemplate',
            'export' => true,
        ];
        $this->relationObject['TP'] = [
            'module' => 'core',
            'class' => 'TimePeriod',
            'export' => true,
        ];
        $this->relationObject['INSTANCE'] = [
            'module' => 'core',
            'class' => 'Instance',
            'export' => true,
        ];
        $this->relationObject['ENGINECFG'] = [
            'module' => 'core',
            'class' => 'EngineCfg',
            'export' => true,
        ];
        $this->relationObject['CENTBROKERCFG'] = [
            'module' => 'core',
            'class' => 'CentbrokerCfg',
            'export' => true,
        ];
        $this->relationObject['RESOURCECFG'] = [
            'module' => 'core',
            'class' => 'ResourceCfg',
            'export' => true,
        ];
        $this->relationObject['ACL'] = [
            'module' => 'core',
            'class' => 'ACL',
            'export' => false,
        ];
        $this->relationObject['ACLGROUP'] = [
            'module' => 'core',
            'class' => 'ACLGroup',
            'export' => true,
        ];
        $this->relationObject['ACLACTION'] = [
            'module' => 'core',
            'class' => 'ACLAction',
            'export' => true,
        ];
        $this->relationObject['ACLMENU'] = [
            'module' => 'core',
            'class' => 'ACLMenu',
            'export' => true,
        ];
        $this->relationObject['ACLRESOURCE'] = [
            'module' => 'core',
            'class' => 'ACLResource',
            'export' => true,
        ];
        $this->relationObject['SETTINGS'] = [
            'module' => 'core',
            'class' => 'Settings',
            'export' => false,
        ];

        // Get objects from modules
        $objectsPath = [];
        $DBRESULT = $this->DB->query('SELECT name FROM modules_informations');

        while ($row = $DBRESULT->fetch()) {
            $objectsPath = array_merge(
                $objectsPath,
                glob(_CENTREON_PATH_ . 'www/modules/' . $row['name'] . '/centreon-clapi/class/*.php')
            );
        }

        foreach ($objectsPath as $objectPath) {
            if (preg_match('/([\w-]+)\/centreon-clapi\/class\/centreon(\w+).class.php/', $objectPath, $matches)) {
                if (isset($matches[1], $matches[2])) {
                    $finalNamespace = substr($matches[1], 0, stripos($matches[1], '-server'));

                    $finalNamespace = implode(
                        '',
                        array_map(
                            function ($n) {
                                return ucfirst($n);
                            },
                            explode('-', $finalNamespace)
                        )
                    );
                    $this->relationObject[strtoupper($matches[2])] = [
                        'module' => $matches[1],
                        'namespace' => $finalNamespace,
                        'class' => $matches[2],
                        'export' => true,
                    ];
                }
            }
        }
    }

    /**
     * @param string|null $user
     * @param string|null $password
     * @param string|null $action
     * @param string|null $centreon_path
     * @param array|null $options
     * @param Container|null $dependencyInjector
     *
     * @throws PDOException
     * @return CentreonAPI|null
     */
    public static function getInstance(
        $user = null,
        $password = null,
        $action = null,
        $centreon_path = null,
        $options = null,
        $dependencyInjector = null
    ) {
        if (is_null(self::$instance)) {
            if (is_null($dependencyInjector)) {
                $dependencyInjector = loadDependencyInjector();
            }

            self::$instance = new CentreonAPI(
                $user,
                $password,
                $action,
                $centreon_path,
                $options,
                $dependencyInjector
            );
        }

        return self::$instance;
    }

    /**
     * @return Container
     */
    public function getDependencyInjector()
    {
        return $this->dependencyInjector;
    }

    /**
     * Set Return Code
     *
     * @param int $returnCode
     * @return void
     */
    public function setReturnCode($returnCode): void
    {
        $this->return_code = $returnCode;
    }

    /**
     * Centreon Object Management
     *
     * @param string $object
     *
     * @return void
     */
    protected function requireLibs($object)
    {
        if ($object != '') {
            if (
                isset($this->relationObject[$object]['class'], $this->relationObject[$object]['module'])

                && ! class_exists("\CentreonClapi\Centreon" . $this->relationObject[$object]['class'])
            ) {
                if ($this->relationObject[$object]['module'] == 'core') {
                    require_once 'centreon' . $this->relationObject[$object]['class'] . '.class.php';
                } else {
                    require_once _CENTREON_PATH_ . '/www/modules/'
                        . $this->relationObject[$object]['module']
                        . '/centreon-clapi/class/centreon'
                        . $this->relationObject[$object]['class']
                        . '.class.php';
                }
            }

            if (
                isset($this->relationObject[$object]['libs'])
                && ! array_walk($this->relationObject[$object]['libs'], 'class_exists')
            ) {
                array_walk($this->relationObject[$object]['libs'], 'require_once');
            }
        } else {
            foreach ($this->relationObject as $sSynonyme => $oObjet) {
                if (
                    isset($oObjet['class'], $oObjet['module'])

                    && ! class_exists("\CentreonClapi\Centreon" . $oObjet['class'])
                ) {
                    if ($oObjet['module'] == 'core') {
                        require_once _CENTREON_PATH_
                            . 'www/class/centreon-clapi/centreon'
                            . $oObjet['class'] . '.class.php';
                    } else {
                        require_once _CENTREON_PATH_
                            . '/www/modules/' . $oObjet['module']
                            . '/centreon-clapi/class/centreon'
                            . $oObjet['class'] . '.class.php';
                    }
                }
                if (isset($oObjet['libs']) && ! array_walk($oObjet['libs'], 'class_exists')) {
                    array_walk($oObjet['libs'], 'require_once');
                }
            }
        }

        // Default class needed
        require_once __DIR__ . '/centreonTimePeriod.class.php';
        require_once __DIR__ . '/centreonACLResources.class.php';
    }

    /**
     * @param string $login
     *
     * @return void
     */
    public function setLogin($login): void
    {
        $this->login = $login;
    }

    /**
     * @param string $password
     *
     * @return void
     */
    public function setPassword($password): void
    {
        $this->password = trim($password);
    }

    /**
     * Check user access and password
     *
     * @param bool $useSha1
     * @param bool $isWorker
     *
     * @throws PDOException
     * @return int|void 1 if user can login
     */
    public function checkUser($useSha1 = false, $isWorker = false)
    {
        if (! isset($this->login) || $this->login == '') {
            echo "ERROR: Can not connect to centreon without login.\n";
            $this->printHelp();

            exit();
        }
        if (! isset($this->password) || $this->password == '') {
            echo 'ERROR: Can not connect to centreon without password.';
            $this->printHelp();
        }

        /**
         * Check Login / Password
         */
        $DBRESULT = $this->DB->prepare(
            "SELECT `contact`.*, `contact_password`.`password` AS `contact_passwd`,
            `contact_password`.`creation_date` AS `password_creation` FROM `contact`
            LEFT JOIN `contact_password` ON `contact_password`.`contact_id` = `contact`.`contact_id`
            WHERE `contact_alias` = :contactAlias
            AND `contact_activate` = '1' AND `contact_register` = '1'
            ORDER BY contact_password.creation_date DESC LIMIT 1"
        );
        $DBRESULT->bindParam(':contactAlias', $this->login, PDO::PARAM_STR);
        $DBRESULT->execute();
        if ($DBRESULT->rowCount()) {
            $row = $DBRESULT->fetchRow();

            if ($row['contact_admin'] == 0) {
                echo "You don't have permissions for CLAPI.\n";

                exit(1);
            }
            $contact = new \CentreonContact($this->DB);
            // Get Security Policy
            $securityPolicy = $contact->getPasswordSecurityPolicy();

            // Remove any blocking if it's not in the policy
            if ($securityPolicy['blocking_duration'] === null) {
                $this->removeBlockingTimeOnUser();
                $row['login_attempts'] = null;
                $row['blocking_time'] = null;
            }

            // Check if user is blocked
            if ($row['blocking_time'] !== null) {
                // If he is block and blocking duration is expired, unblock him
                if ((int) $row['blocking_time'] + (int) $securityPolicy['blocking_duration'] < time()) {
                    $this->removeBlockingTimeOnUser();
                    $row['login_attempts'] = null;
                    $row['blocking_time'] = null;
                } else {
                    $now = new DateTime();
                    $expirationDate = (new DateTime())->setTimestamp(
                        $row['blocking_time'] + $securityPolicy['blocking_duration']
                    );
                    $interval = (date_diff($now, $expirationDate))->format('%Dd %Hh %Im %Ss');
                    echo "Authentication failed.\n";
                    $CentreonLog = new CentreonLog();
                    $CentreonLog->insertLog(
                        1,
                        "Authentication failed for '" . $row['contact_alias'] . "',"
                        . " max login attempts has been reached. {$interval} left\n"
                    );

                    exit(1);
                }
            }

            $passwordExpirationDelay = $securityPolicy['password_expiration']['expiration_delay'];
            if (
                $row['contact_auth_type'] !== CentreonAuth::AUTH_TYPE_LDAP
                && $passwordExpirationDelay !== null
                && (int) $row['password_creation'] + (int) $passwordExpirationDelay < time()
                // Do not check expiration for excluded users of local security policy
                && ! in_array($row['contact_alias'], $securityPolicy['password_expiration']['excluded_users'])
            ) {
                echo "Unable to login, your password has expired.\n";

                exit(1);
            }

            // Update password from md5 to bcrypt if old md5 password is valid.
            if (
                (str_starts_with($row['contact_passwd'], 'md5__')
                && $row['contact_passwd'] === $this->dependencyInjector['utils']->encodePass($this->password, 'md5'))
                || 'md5__' . $row['contact_passwd'] === $this->dependencyInjector['utils']->encodePass(
                    $this->password,
                    'md5'
                )
            ) {
                $hashedPassword = password_hash($this->password, CentreonAuth::PASSWORD_HASH_ALGORITHM);
                $contact->replacePasswordByContactId(
                    (int) $row['contact_id'],
                    $row['contact_passwd'],
                    $hashedPassword
                );
                CentreonUtils::setUserId($row['contact_id']);
                $this->removeBlockingTimeOnUser();

                return 1;
            }
            if (password_verify($this->password, $row['contact_passwd'])) {
                CentreonUtils::setUserId($row['contact_id']);
                $this->removeBlockingTimeOnUser();

                return 1;
            }
            if ($row['contact_auth_type'] == 'ldap') {
                $CentreonLog = new CentreonUserLog(-1, $this->DB);
                $centreonAuth = new CentreonAuthLDAP(
                    $this->DB,
                    $CentreonLog,
                    $this->login,
                    $this->password,
                    $row,
                    $row['ar_id']
                );
                if ($centreonAuth->checkPassword() == CentreonAuth::PASSWORD_VALID) {
                    CentreonUtils::setUserId($row['contact_id']);

                    return 1;
                }
            }
            if ($securityPolicy['attempts'] !== null && $securityPolicy['blocking_duration'] !== null) {
                $this->exitOnInvalidCredentials(
                    (int) $row['login_attempts'],
                    (int) $securityPolicy['attempts'],
                    (int) $securityPolicy['blocking_duration']
                );
            }
        }
        echo "Authentication failed.\n";

        exit(1);
    }

    /**
     * return (print) a "\n"
     *
     * @return void
     */
    public function endOfLine(): void
    {
        echo "\n";
    }

    /**
     * close the current action
     *
     * @return void
     */
    public function close(): void
    {
        echo "\n";

        exit($this->return_code);
    }

    /**
     * Print usage for using CLAPI ...
     *
     * @param bool $dbOk | whether db is ok
     * @param int $returnCode
     */
    public function printHelp($dbOk = true, $returnCode = 0): void
    {
        if ($dbOk) {
            $this->printLegals();
        }
        echo "This software comes with ABSOLUTELY NO WARRANTY. This is free software,\n";
        echo "and you are welcome to modify and redistribute it under the GPL license\n\n";
        echo "usage: ./centreon -u <LOGIN> -p <PASSWORD> [-s] -o <OBJECT> -a <ACTION> [-v]\n";
        echo "  -s     Use SHA1 on password (default is MD5)\n";
        echo "  -v     variables \n";
        echo "  -h     Print help \n";
        echo "  -V     Print version \n";
        echo "  -o     Object type \n";
        echo "  -a     Launch action on Centreon\n";
        echo "     Actions are the followings :\n";
        echo "       - POLLERGENERATE: Build nagios configuration for a poller (poller id in -v parameters)\n";
        echo "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a POLLERGENERATE -v 1 \n";
        echo "       - POLLERTEST: Test nagios configuration for a poller (poller id in -v parameters)\n";
        echo "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a POLLERTEST -v 1 \n";
        echo "       - CFGMOVE: move nagios configuration for a poller to final directory\n";
        echo "         (poller id in -v parameters)\n";
        echo "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a CFGMOVE -v 1 \n";
        echo "       - POLLERRESTART: Restart a poller (poller id in -v parameters)\n";
        echo "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a POLLERRESTART -v 1 \n";
        echo "       - POLLERRELOAD: Reload a poller (poller id in -v parameters)\n";
        echo "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a POLLERRELOAD -v 1 \n";
        echo "       - POLLERLIST: list all pollers\n";
        echo "           #> ./centreon -u <LOGIN> -p <PASSWORD> -a POLLERLIST\n";
        echo "\n";
        echo "   For more information about configuration objects, please refer to CLAPI wiki:\n";
        echo "      - https://docs.centreon.com/docs/api/clapi/ \n";
        echo "\n";
        echo "Notes:\n";
        echo "  - Actions can be written in lowercase chars\n";
        echo "  - LOGIN and PASSWORD is an admin account of Centreon\n";
        echo "\n";

        exit($returnCode);
    }

    /**
     * Get variable passed in parameters
     *
     * @param string $str
     *
     * @return string
     */
    public function getVar($str)
    {
        $res = explode('=', $str);

        return $res[1];
    }

    /**
     * Init XML Flow
     *
     * @return void
     */
    public function initXML(): void
    {
        $this->xmlObj = new CentreonXML();
    }

    /**
     * Main function : Launch action
     *
     * @param bool $exit If exit or return the return code
     *
     * @return int|void
     */
    public function launchAction($exit = true)
    {
        $action = strtoupper($this->action);

        // Debug
        if ($this->debug) {
            echo "DEBUG : {$action}\n";
        }

        // Check method availability before using it.
        if ($this->object) {
            $isService = $this->dependencyInjector['centreon.clapi']->has($this->object);
            if ($isService === true) {
                $objName = $this->dependencyInjector['centreon.clapi']->get($this->object);
            } else {
                // Require needed class
                $this->requireLibs($this->object);

                // Check class declaration
                if (isset($this->relationObject[$this->object]['class'])) {
                    if ($this->relationObject[$this->object]['module'] === 'core') {
                        $objName = "\CentreonClapi\centreon" . $this->relationObject[$this->object]['class'];
                    } else {
                        $objName = $this->relationObject[$this->object]['namespace'] . "\CentreonClapi\Centreon"
                            . $this->relationObject[$this->object]['class'];
                    }
                } else {
                    $objName = '';
                }

                if (! isset($this->relationObject[$this->object]['class']) || ! class_exists($objName)) {
                    echo "Object {$this->object} not found in Centreon API.\n";

                    return 1;
                }
            }
            $obj = new $objName($this->dependencyInjector);
            if (method_exists($obj, $action) || method_exists($obj, '__call')) {
                $this->return_code = $obj->{$action}($this->variables);
            } else {
                echo "Method not implemented into Centreon API.\n";

                return 1;
            }
        } elseif (method_exists($this, $action)) {
            $this->return_code = $this->{$action}();
        } else {
            echo "Method not implemented into Centreon API.\n";
            $this->return_code = 1;
        }

        if ($exit) {
            echo 'Return code end : ' . $this->return_code . "\n";

            exit($this->return_code);
        }

        return $this->return_code;
    }

    /**
     * Import Scenario file
     *
     * @param string $filename
     *
     * @return int
     */
    public function import($filename)
    {
        $globalReturn = 0;

        $this->fileExists($filename);

        // Open File in order to read it.
        $handle = fopen($filename, 'r');
        if ($handle) {
            $i = 0;
            while (($string = fgets($handle)) !== false) {
                $i++;

                $string = trim($string);
                if (
                    $string === ''
                    || str_starts_with($string, '#')
                    || str_starts_with($string, '{OBJECT_TYPE}')
                ) {
                    continue;
                }

                $tab = explode(';', $string, 3);
                $this->object = trim($tab[0]);
                $this->action = trim($tab[1]);
                $this->variables = trim($tab[2]);

                if ($this->debug == 1) {
                    echo 'Object : ' . $this->object . "\n";
                    echo 'Action : ' . $this->action . "\n";
                    echo 'VARIABLES : ' . $this->variables . "\n\n";
                }
                try {
                    $this->launchActionForImport();
                } catch (CentreonClapiException|Exception $e) {
                    echo "Line {$i} : " . $e->getMessage() . "\n";
                }
                if ($this->return_code) {
                    $globalReturn = 1;
                }
            }
            fclose($handle);
        }

        return $globalReturn;
    }

    /**
     * @return int|void
     */
    public function launchActionForImport()
    {
        $action = strtoupper($this->action);
        // Debug
        if ($this->debug) {
            echo "DEBUG : {$action}\n";
        }

        // Check method availability before using it.
        if ($this->object) {
            $this->iniObject($this->object);

            // Check class declaration
            $obj = $this->objectTable[$this->object];
            if (method_exists($obj, $action) || method_exists($obj, '__call')) {
                $this->return_code = $obj->{$action}($this->variables);
            } else {
                echo "Method not implemented into Centreon API.\n";

                return 1;
            }
        } elseif (method_exists($this, $action) || method_exists($this, '__call')) {
            $this->return_code = $this->{$action}();
        } else {
            echo "Method not implemented into Centreon API.\n";
            $this->return_code = 1;
        }
    }

    /**
     * @param $newOption
     *
     * @return void
     */
    public function setOption($newOption): void
    {
        $this->options = $newOption;
    }

    /**
     * @param $newVariables
     *
     * @return void
     */
    public function setVariables($newVariables): void
    {
        $this->variables = $newVariables;
    }

    /**
     * @param $newPath
     *
     * @return void
     */
    public function setCentreonPath($newPath): void
    {
        $this->centreon_path = $newPath;
    }

    /**
     * Export All configuration
     *
     * @param bool $withoutClose disable using of PHP exit function (default: false)
     *
     * @return int|void
     */
    public function export($withoutClose = false)
    {
        $this->requireLibs('');

        $this->sortClassExport();

        $this->initAllObjects();

        if (isset($this->options['select'])) {
            CentreonExported::getInstance()->setFilter(1);
            CentreonExported::getInstance()->setOptions($this->options);
            $selected = $this->options['select'];

            if (! is_array($this->options['select'])) {
                $selected = [$this->options['select']];
            }

            foreach ($selected as $select) {
                $splits = explode(';', $select);

                $splits[0] ??= null;
                $splits[1] ??= null;

                if (! isset($this->objectTable[$splits[0]])) {
                    echo "Unknown object : {$splits[0]}\n";
                    $this->setReturnCode(1);

                    if ($withoutClose === false) {
                        $this->close();
                    } else {
                        return;
                    }
                } elseif (! is_null($splits[1])) {
                    $name = $splits[1];
                    if (isset($splits[2])) {
                        $name .= ';' . $splits[2];
                    }
                    if ($this->objectTable[$splits[0]]->getObjectId($name, CentreonObject::MULTIPLE_VALUE) == 0) {
                        echo "Unknown object : {$splits[0]};{$splits[1]}\n";
                        $this->setReturnCode(1);
                        if ($withoutClose === false) {
                            $this->close();
                        } else {
                            return;
                        }
                    } else {
                        $this->objectTable[$splits[0]]->export($name);
                    }
                } else {
                    $this->objectTable[$splits[0]]->export();
                }
            }

            return $this->return_code;
        }
        // header
        echo "{OBJECT_TYPE}{$this->delim}{COMMAND}{$this->delim}{PARAMETERS}\n";
        if (count($this->aExport) > 0) {
            foreach ($this->aExport as $oObjet) {
                if (method_exists($this->objectTable[$oObjet], 'export')) {
                    $this->objectTable[$oObjet]->export();
                }
            }
        }

    }

    /**
     * @param string $objname
     *
     * @return void
     */
    private function iniObject($objname): void
    {
        $className = '';
        if (
            isset($this->relationObject[$objname]['namespace'])
            && $this->relationObject[$objname]['namespace']
        ) {
            $className .= '\\' . $this->relationObject[$objname]['namespace'];
        }
        $className .= '\CentreonClapi\centreon' . $this->relationObject[$objname]['class'];
        $this->requireLibs($objname);
        $this->objectTable[$objname] = new $className($this->dependencyInjector, $objname);
    }

    /**
     * Init All object instance in order to export all informations
     *
     * @return void
     */
    private function initAllObjects(): void
    {
        if (count($this->aExport) > 0) {
            foreach ($this->aExport as $oObjet) {
                $this->iniObject($oObjet);
            }
        }
    }

    /**
     * Check if file exists
     *
     * @param string $filename
     *
     * @return void
     */
    private function fileExists($filename): void
    {
        if (! file_exists($filename)) {
            echo "{$filename} : File doesn't exists\n";

            exit(1);
        }
    }

    /**
     * Print centreon version and legal use
     *
     * @throws PDOException
     * @return void
     */
    public function printLegals(): void
    {
        $DBRESULT = $this->DB->query("SELECT * FROM informations WHERE `key` = 'version'");
        $data = $DBRESULT->fetchRow();
        echo 'Centreon version ' . $data['value'] . ' - ';
        echo "Copyright Centreon - www.centreon.com\n";
        unset($data);
    }

    /**
     * Print centreon version
     *
     * @throws PDOException
     * @return void
     */
    public function printVersion(): void
    {
        $res = $this->DB->query("SELECT * FROM informations WHERE `key` = 'version'");
        $data = $res->fetchRow();
        echo 'Centreon version ' . $data['value'] . "\n";
    }

    // API Possibilities

    /**
     * List all poller declared in Centreon
     *
     * @throws PDOException
     * @throws LogicException
     * @return int
     */
    public function POLLERLIST()
    {
        $poller = new CentreonConfigPoller($this->centreon_path, $this->dependencyInjector);

        return $poller->getPollerList($this->format);
    }

    /**
     * Launch poller restart
     *
     * @throws CentreonClapiException
     * @throws PDOException
     * @throws LogicException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     * @return int|null
     */
    public function POLLERRESTART()
    {
        $poller = new CentreonConfigPoller($this->centreon_path, $this->dependencyInjector);

        return $poller->pollerRestart($this->variables);
    }

    /**
     * Launch poller reload
     *
     * @throws CentreonClapiException
     * @throws PDOException
     * @throws LogicException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     * @return int|mixed
     */
    public function POLLERRELOAD()
    {
        $poller = new CentreonConfigPoller($this->centreon_path, $this->dependencyInjector);

        return $poller->pollerReload($this->variables);
    }

    /**
     * Launch poller configuration files generation
     *
     * @throws CentreonClapiException
     * @throws PDOException
     * @throws LogicException
     * @return int
     */
    public function POLLERGENERATE()
    {
        $poller = new CentreonConfigPoller($this->centreon_path, $this->dependencyInjector);

        return $poller->pollerGenerate($this->variables, $this->login, $this->password);
    }

    /**
     * Launch poller configuration test
     *
     * @throws CentreonClapiException
     * @throws PDOException
     * @throws LogicException
     * @return int|null
     */
    public function POLLERTEST()
    {
        $poller = new CentreonConfigPoller($this->centreon_path, $this->dependencyInjector);

        return $poller->pollerTest($this->format, $this->variables);
    }

    /**
     * Execute the post generation command
     *
     * @throws CentreonClapiException
     * @throws PDOException
     * @throws LogicException
     * @return int
     */
    public function POLLEREXECCMD()
    {
        $poller = new CentreonConfigPoller($this->centreon_path, $this->dependencyInjector);

        return $poller->execCmd($this->variables);
    }

    /**
     * move configuration files into final directory
     *
     * @throws CentreonClapiException
     * @throws PDOException
     * @throws LogicException
     * @return int|null
     */
    public function CFGMOVE()
    {
        $poller = new CentreonConfigPoller($this->centreon_path, $this->dependencyInjector);

        return $poller->cfgMove($this->variables);
    }

    /**
     * Send trap configuration file to poller
     *
     * @throws CentreonClapiException
     * @throws PDOException
     * @throws LogicException
     * @return int|null
     */
    public function SENDTRAPCFG()
    {
        $poller = new CentreonConfigPoller($this->centreon_path, $this->dependencyInjector);

        return $poller->sendTrapCfg($this->variables);
    }

    /**
     * Apply configuration Generation + move + reload
     *
     * @throws CentreonClapiException
     * @throws PDOException
     * @throws LogicException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     * @return int|mixed|null
     */
    public function APPLYCFG()
    {
        /**
         * Display time for logs
         */
        echo date('Y-m-d H:i:s') . " - APPLYCFG\n";

        /**
         * Launch Actions
         */
        $poller = new CentreonConfigPoller($this->centreon_path, $this->dependencyInjector);
        $this->return_code = $poller->pollerGenerate($this->variables, $this->login, $this->password);
        $this->endOfLine();
        if ($this->return_code == 0) {
            $this->return_code = $poller->pollerTest($this->format, $this->variables);
            $this->endOfLine();
        }
        if ($this->return_code == 0) {
            $this->return_code = $poller->cfgMove($this->variables);
            $this->endOfLine();
        }
        if ($this->return_code == 0) {
            $this->return_code = $poller->pollerReload($this->variables);
        }
        if ($this->return_code == 0) {
            $this->return_code = $poller->execCmd($this->variables);
        }

        return $this->return_code;
    }

    /**
     * This method sort the objects to export
     *
     * @return void
     */
    public function sortClassExport(): void
    {
        if (isset($this->relationObject) && is_array(($this->relationObject))) {
            $aObject = $this->relationObject;
            while ($oObjet = array_slice($aObject, -1, 1, true)) {
                $key = key($oObjet);
                if (
                    isset($oObjet[$key]['class'])
                    && $oObjet[$key]['export'] === true
                    && ! in_array($key, $this->aExport)
                ) {
                    $objName = '';
                    if (isset($oObjet[$key]['namespace'])) {
                        $objName = '\\' . $oObjet[$key]['namespace'];
                    }
                    $objName .= '\CentreonClapi\Centreon' . $oObjet[$key]['class'];
                    $objVars = get_class_vars($objName);

                    if (isset($objVars['aDepends'])) {
                        $bInsert = true;
                        foreach ($objVars['aDepends'] as $item => $oDependence) {
                            $keyDep = strtoupper($oDependence);
                            if (! in_array($keyDep, $this->aExport)) {
                                $bInsert = false;
                            }
                        }

                        if ($bInsert) {
                            $this->aExport[] = $key;
                            array_pop($aObject);
                        } else {
                            $aObject = array_merge($oObjet, $aObject);
                        }
                    } else {
                        $this->aExport[] = $key;
                        array_pop($aObject);
                    }
                } else {
                    array_pop($aObject);
                }
            }
        }
    }

    /**
     * Increment login attempts for user.
     *
     * @param int $contactLoginAttempts
     *
     * @throws PDOException
     * @return int
     */
    private function incrementLoginAttempts(int $contactLoginAttempts): int
    {
        // Increments login attempts for user
        $contactLoginAttempts++;

        // update User attempts
        $attemptStatement = $this->DB->prepare(
            'UPDATE contact SET login_attempts = :loginAttempts WHERE contact_alias = :contactAlias'
        );
        $attemptStatement->bindValue(':loginAttempts', $contactLoginAttempts, PDO::PARAM_INT);
        $attemptStatement->bindValue(':contactAlias', $this->login, PDO::PARAM_STR);
        $attemptStatement->execute();

        return $contactLoginAttempts;
    }

    /**
     * Block login for user.
     *
     * @throws PDOException
     * @return void
     */
    private function blockLoginForUser(): void
    {
        $blockLoginStatement = $this->DB->prepare(
            'UPDATE contact SET blocking_time = :blockingTime WHERE contact_alias = :contactAlias'
        );
        $blockLoginStatement->bindValue(':blockingTime', time(), PDO::PARAM_INT);
        $blockLoginStatement->bindValue(':contactAlias', $this->login, PDO::PARAM_STR);
        $blockLoginStatement->execute();
    }

    /**
     * Exit with invalid credentials message.
     *
     * @param int $contactLoginAttempts
     * @param int $securityPolicyAttempts
     * @param int $blockingDuration
     *
     * @throws PDOException
     */
    private function exitOnInvalidCredentials(
        int $contactLoginAttempts,
        int $securityPolicyAttempts,
        int $blockingDuration
    ): void {
        $CentreonLog = new CentreonLog();
        $loginAttempts = $this->incrementLoginAttempts($contactLoginAttempts);
        if ($loginAttempts === $securityPolicyAttempts) {
            $this->blockLoginForUser();
            echo "Authentication failed.\n";
            $CentreonLog->insertLog(
                1,
                "Authentication failed. Max attempts has been reached, User can't login for "
                . "{$blockingDuration} seconds."
            );

            exit(1);
        }
        $attemptRemaining = $securityPolicyAttempts - $loginAttempts;
        echo "Authentication failed.\n";
        $CentreonLog->insertLog(1, "Authentication failed. {$attemptRemaining} attempt(s) remaining");

        exit(1);
    }

    /**
     * Remove the blocking time and login attemps.
     *
     * @throws PDOException
     * @return void
     */
    private function removeBlockingTimeOnUser(): void
    {
        $unblockStatement = $this->DB->prepare(
            'UPDATE contact SET blocking_time = NULL, login_attempts = NULL '
                . 'WHERE contact_alias = :contactAlias'
        );
        $unblockStatement->bindValue(':contactAlias', $this->login, PDO::PARAM_STR);
        $unblockStatement->execute();
    }
}
