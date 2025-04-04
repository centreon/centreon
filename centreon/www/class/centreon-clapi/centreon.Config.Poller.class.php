<?php

/*
 * Copyright 2005-2020 CENTREON
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
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
 * As a special exception, the copyright holders of this program give CENTREON
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of CENTREON choice, provided that
 * CENTREON also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

namespace CentreonClapi;

use App\Kernel;
use Centreon\Domain\Entity\Task;
use CentreonDB;
use CentreonRemote\ServiceProvider;
use Core\Domain\Engine\Model\EngineCommandGenerator;
use Exception;
use Generate;
use LogicException;
use PDO;
use PDOException;
use Pimple\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

require_once "centreonUtils.class.php";
require_once "centreonClapiException.class.php";
require_once _CENTREON_PATH_ . 'www/class/config-generate/generate.class.php';

/**
 * Class
 *
 * @class CentreonConfigPoller
 * @package CentreonClapi
 */
class CentreonConfigPoller
{
    public const MISSING_POLLER_ID = "Missing poller ID";
    public const UNKNOWN_POLLER_ID = "Unknown poller ID";
    public const CENTREON_SYSTEM_USER = 'centreon';

    /** @var CentreonDB */
    private $DB;
    /** @var CentreonDB */
    private $DBC;
    /** @var Container */
    private $dependencyInjector;
    /** @var int[] */
    private $resultTest = ["warning" => 0, "errors" => 0];
    /** @var string */
    private $brokerCachePath = _CENTREON_CACHEDIR_ . "/config/broker/";
    /** @var string */
    private $engineCachePath = _CENTREON_CACHEDIR_ . "/config/engine/";
    /** @var string */
    private $vmWareCachePath = _CENTREON_CACHEDIR_ . "/config/vmware/";
    /** @var string */
    private $centreon_path;
    /** @var EngineCommandGenerator|null */
    private ?EngineCommandGenerator $commandGenerator = null;
    /** @var ContainerInterface */
    private ContainerInterface $container;

    /**
     * CentreonConfigPoller constructor
     *
     * @param $centreon_path
     * @param Container $dependencyInjector
     *
     * @throws LogicException
     */
    public function __construct($centreon_path, Container $dependencyInjector)
    {
        $this->dependencyInjector = $dependencyInjector;
        $this->DB = $this->dependencyInjector["configuration_db"];
        $this->DBC = $this->dependencyInjector["realtime_db"];
        $this->centreon_path = $centreon_path;

        $kernel = new Kernel('prod', false);
        $kernel->boot();
        $this->container = $kernel->getContainer();
    }

    /**
     * Write command to centcore pipe, using the dynamic centcore pipe file
     * when possible
     *
     * @param string $cmd
     * @param int $id
     * @return int
     */
    private function writeToCentcorePipe($cmd, $id): int
    {
        if (is_dir(_CENTREON_VARLIB_ . '/centcore')) {
            $pipe = _CENTREON_VARLIB_ . '/centcore/' . hrtime(true) . '-externalcommand.cmd';
        } else {
            $pipe = _CENTREON_VARLIB_ . '/centcore.cmd';
        }
        $fullCommand = sprintf("%s:%d" . PHP_EOL, $cmd, $id);
        $result = file_put_contents($pipe, $fullCommand, FILE_APPEND);
        return ($result !== false) ? 0 : 1;
    }

    /**
     * Check for the existence of poller with ID or name $poller, and return
     * the ID of that poller. If the poller does not exist, raise an exception.
     *
     * @param string|int $poller
     *
     * @return int
     * @throws CentreonClapiException
     * @throws PDOException
     */
    private function ensurePollerId($poller)
    {
        if (is_numeric($poller)) {
            $statement = $this->DB->prepare("SELECT id FROM nagios_server WHERE id = :poller");
            $statement->bindValue(':poller', $poller, PDO::PARAM_INT);
        } else {
            $statement = $this->DB->prepare("SELECT id FROM nagios_server WHERE name = :poller");
            $statement->bindValue(':poller', $poller, PDO::PARAM_STR);
        }

        $statement->execute();
        if ($statement->rowCount() > 0) {
            $row = $statement->fetchRow();
            return $row['id'];
        } else {
            throw new CentreonClapiException(self::UNKNOWN_POLLER_ID);
        }
    }

    /**
     * @param string $format
     *
     * @return int
     * @throws PDOException
     */
    public function getPollerList($format)
    {
        $DBRESULT = $this->DB->query("SELECT id,name FROM nagios_server WHERE ns_activate = '1' ORDER BY id");
        if ($format == "xml") {
            print "";
        }
        print "poller_id;name\n";
        while ($data = $DBRESULT->fetchRow()) {
            print $data["id"] . ";" . $data["name"] . "\n";
        }
        $DBRESULT->closeCursor();
        return 0;
    }

    /**
     * @param $variables
     *
     * @return int
     * @throws CentreonClapiException
     * @throws PDOException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function pollerReload($variables)
    {
        if (! isset($variables)) {
            echo "Cannot get poller";
            return 1;
        }

        $poller_id = $this->ensurePollerId($variables);

        $statement = $this->DB->prepare(
            "SELECT * FROM `nagios_server` WHERE `id` = :poller_id  LIMIT 1"
        );
        $statement->bindValue(':poller_id', (int) $poller_id, PDO::PARAM_INT);
        $statement->execute();
        $host = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        $this->commandGenerator = $this->container->get(EngineCommandGenerator::class);
        $reloadCommand = $this->commandGenerator->getEngineCommand('RELOAD');
        $return_code = $this->writeToCentcorePipe($reloadCommand, $host["id"]);
        if ($return_code === 1) {
            echo "Error while writing the command {$reloadCommand} in centcore pipe file for host id {$host["id"]}" . PHP_EOL;
            return $return_code;
        }
        $return_code = $this->writeToCentcorePipe('RELOADBROKER', $host["id"]);
        if ($return_code === 1) {
            echo "Error while writing the command RELOADBROKER in centcore pipe file for host id {$host["id"]}" . PHP_EOL;
            return $return_code;
        }
        $msg_restart = _("OK: A reload signal has been sent to '" . $host["name"] . "'");
        echo $msg_restart . "\n";
        $statement = $this->DB->prepare(
            "UPDATE `nagios_server` SET `last_restart` = :last_restart, `updated` = '0' WHERE `id` = :poller_id LIMIT 1"
        );
        $statement->bindValue(':last_restart', time(), PDO::PARAM_INT);
        $statement->bindValue(':poller_id', (int) $poller_id, PDO::PARAM_INT);
        $statement->execute();
        return $return_code;
    }

    /**
     * Execute post generation command
     *
     * @param int $pollerId
     *
     * @return int
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function execCmd($pollerId)
    {
        $instanceClassFile = $this->centreon_path . 'www/class/centreonInstance.class.php';
        if (!is_file($instanceClassFile)) {
            throw new CentreonClapiException('This action is not available in the version of Centreon you are using');
        }
        require_once $instanceClassFile;

        $pollerId = $this->ensurePollerId($pollerId);

        $instanceObj = new \CentreonInstance($this->DB);
        $cmds = $instanceObj->getCommandData($pollerId);
        $result = 0;
        foreach ($cmds as $cmd) {
            echo "Executing command {$cmd['command_name']}... ";
            exec($cmd['command_line'], $output, $cmdResult);
            if ($cmdResult) {
                $resultStr = "Error: {$output}";
                $result += $cmdResult;
            } else {
                $resultStr = "OK";
            }
            echo "{$resultStr}\n";
        }
        // if result > 0, return 1, return 0 otherwise
        return ($result ? 1 : 0);
    }

    /**
     * @param $variables
     *
     * @return int
     * @throws CentreonClapiException
     * @throws PDOException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     */
    public function pollerRestart($variables)
    {
        if (! isset($variables)) {
            echo "Cannot get poller";
            return 1;
        }

        $poller_id = $this->ensurePollerId($variables);

        $statement = $this->DB->prepare(
            "SELECT * FROM `nagios_server` WHERE `id` = :poller_id  LIMIT 1"
        );
        $statement->bindValue(':poller_id', (int) $poller_id, PDO::PARAM_INT);
        $statement->execute();
        $host = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        $this->commandGenerator = $this->container->get(EngineCommandGenerator::class);
        $restartCommand = $this->commandGenerator->getEngineCommand('RESTART');
        $return_code = $this->writeToCentcorePipe($restartCommand, $host["id"]);
        if ($return_code === 1) {
            echo "Error while writing the command {$restartCommand} in centcore pipe file for host id {$host["id"]}" . PHP_EOL;
            return $return_code;
        }
        $return_code = $this->writeToCentcorePipe('RELOADBROKER', $host["id"]);
        if ($return_code === 1) {
            echo "Error while writing the command RELOADBROKER in centcore pipe file for host id {$host["id"]}" . PHP_EOL;
            return $return_code;
        }
        $msg_restart = _("OK: A restart signal has been sent to '" . $host["name"] . "'");
        echo $msg_restart . "\n";
        $statement = $this->DB->prepare(
            "UPDATE `nagios_server` SET `last_restart` = :last_restart, `updated` = '0' WHERE `id` = :poller_id LIMIT 1"
        );
        $statement->bindValue(':last_restart', time(), PDO::PARAM_INT);
        $statement->bindValue(':poller_id', (int) $poller_id, PDO::PARAM_INT);
        $statement->execute();
        return $return_code;
    }

    /**
     * @param $format
     * @param $variables
     *
     * @return int|void
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function pollerTest($format, $variables)
    {
        if (!isset($variables)) {
            print "Cannot get poller";
            exit(1);
        }

        $idPoller = $this->ensurePollerId($variables);

        /**
         * Get Nagios Bin
         */
        $DBRESULT_Servers = $this->DB->query(
            "SELECT `nagios_bin` FROM `nagios_server` WHERE `localhost` = '1' ORDER BY `ns_activate` DESC LIMIT 1"
        );
        $nagios_bin = $DBRESULT_Servers->fetchRow();
        $DBRESULT_Servers->closeCursor();

        /*
         * Launch test command
         */
        if (isset($nagios_bin["nagios_bin"])) {
            exec(
                escapeshellcmd(
                    $nagios_bin["nagios_bin"] . " -v "
                    . $this->engineCachePath . '/' . $idPoller . "/centengine.DEBUG"
                ),
                $lines,
                $return_code
            );
        } else {
            throw new CentreonClapiException("Can't find engine binary");
        }

        $msg_debug = "";
        foreach ($lines as $line) {
            if (
                strncmp($line, "Processing object config file", strlen("Processing object config file"))
                && strncmp($line, "Website: http://www.nagios.org", strlen("Website: http://www.nagios.org"))
            ) {
                $msg_debug .= $line . "\n";

                /**
                 * Detect Errors
                 */
                if (preg_match("/Total Warnings: ([0-9])*/", $line, $matches)) {
                    if (isset($matches[1])) {
                        $this->resultTest["warning"] = $matches[1];
                    }
                }
                if (preg_match("/Total Errors: ([0-9])*/", $line, $matches)) {
                    if (isset($matches[1])) {
                        $this->resultTest["errors"] = $matches[1];
                    }
                }
                if (preg_match("/^Error:/", $line, $matches)) {
                    $this->resultTest["errors"]++;
                }
                if (preg_match("/^Errors:/", $line, $matches)) {
                    $this->resultTest["errors"]++;
                }
            }
        }
        if ($this->resultTest["errors"] != 0) {
            print "Error: Centreon Poller $variables cannot restart. configuration broker. Please see debug bellow :\n";
            print "-----------------------------------------------------------"
                . "----------------------------------------\n";
            print $msg_debug . "\n";
            print "---------------------------------------------------"
                . "------------------------------------------------\n";
        } elseif ($this->resultTest["warning"] != 0) {
            print "Warning: Centreon Poller $variables can restart but "
                . "configuration is not optimal. Please see debug bellow :\n";
            print "-----------------------------------------------"
                . "----------------------------------------------------\n";
            print $msg_debug . "\n";
            print "------------------------------------------------"
                . "---------------------------------------------------\n";
        } elseif ($return_code) {
            print implode("\n", $lines);
        } else {
            print "OK: Centreon Poller $variables can restart without problem...\n";
        }
        return $return_code;
    }

    /**
     *
     * Generate configuration files for a specific poller
     *
     * @param $variables
     * @param string $login
     * @param string $password
     *
     * @return int
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function pollerGenerate($variables, $login, $password)
    {

        $config_generate = new Generate($this->dependencyInjector);

        $poller_id = $this->ensurePollerId($variables);
        $config_generate->configPollerFromId($poller_id, $login);

        /* Change files owner */
        $apacheUser = $this->getApacheUser();

        $setFilesOwner = 1;
        if (posix_getuid() === 0 && $apacheUser != "") {
            /* Change engine Path mod */
            chown($this->engineCachePath . "/$poller_id", $apacheUser);
            chgrp($this->engineCachePath . "/$poller_id", $apacheUser);

            foreach (glob($this->engineCachePath . "/$poller_id/*.cfg") as $file) {
                chown($file, $apacheUser);
                chgrp($file, $apacheUser);
            }

            foreach (glob($this->engineCachePath . "/$poller_id/*.DEBUG") as $file) {
                chown($file, $apacheUser);
                chgrp($file, $apacheUser);
            }

            /* Change broker Path mod */
            chown($this->brokerCachePath . "/$poller_id", $apacheUser);
            chgrp($this->brokerCachePath . "/$poller_id", $apacheUser);

            foreach (glob($this->brokerCachePath . "/$poller_id/*.{xml,json,cfg}", GLOB_BRACE) as $file) {
                chown($file, $apacheUser);
                chgrp($file, $apacheUser);
            }

            /* Change VMWare Path mod */
            chown($this->vmWareCachePath . "/$poller_id", $apacheUser);
            chgrp($this->vmWareCachePath . "/$poller_id", self::CENTREON_SYSTEM_USER);

            /**
             * Change VMWare files owner to '660 apache centreon'
             * RW for centreon group are necessary for Gorgone Daemon.
             */
            foreach (glob($this->vmWareCachePath . "/$poller_id/*.{json}", GLOB_BRACE) as $file) {
                chmod($file, 0660);
                chown($file, $apacheUser);
                chgrp($file, self::CENTREON_SYSTEM_USER);
            }
        } else {
            $setFilesOwner = 0;
        }

        if ($setFilesOwner == 0) {
            print "Cannot set configuration file owner after the generation. \n";
            print "Please check that files in the followings directory are writable by apache user : "
                . $this->engineCachePath . "/$poller_id/\n";
            print "Please check that files in the followings directory are writable by apache user : "
                . $this->brokerCachePath . "/$poller_id/\n";
        }

        print "Configuration files generated for poller '" . $variables . "'\n";
        return 0;
    }

    /**
     *
     * Move configuration files to servers
     *
     * @param mixed|null $variables
     *
     * @return int|void
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function cfgMove($variables = null)
    {
        global $pearDB, $pearDBO;
        $pearDB = $this->DB;
        $pearDBO = $this->DBC;

        require_once _CENTREON_PATH_ . "www/include/configuration/configGenerate/DB-Func.php";
        if (!isset($variables)) {
            print "Cannot get poller";
            exit(1);
        }

        $return = 0;

        $pollerId = $this->ensurePollerId($variables);

        $statement = $pearDB->prepare("SELECT * FROM `nagios_server` WHERE `id` = :pollerId");
        $statement->bindValue(':pollerId', $pollerId, PDO::PARAM_INT);
        $statement->execute();
        $host = $statement->fetchRow();
        $statement->closeCursor();

        // Move files
        $msg_copy = "";
        if (isset($host['localhost']) && $host['localhost'] == 1) {
            /* Get Apache user name */
            $apacheUser = $this->getApacheUser();

            $statement = $pearDB->prepare("SELECT `cfg_dir` FROM `cfg_nagios` WHERE `nagios_server_id` = :pollerId");
            $statement->bindValue(':pollerId', $pollerId, PDO::PARAM_INT);
            $statement->execute();
            $Nagioscfg = $statement->fetchRow();
            $statement->closeCursor();

            foreach (glob($this->engineCachePath . '/' . $pollerId . '/*.{json,cfg}', GLOB_BRACE) as $filename) {
                $bool = @copy($filename, $Nagioscfg["cfg_dir"] . "/" . basename($filename));
                $result = explode("/", $filename);
                $filename = array_pop($result);
                if (!$bool) {
                    $msg_copy .= $this->displayCopyingFile($filename, " - " . _("movement") . " KO");
                    $return = 1;
                }
            }

            /* Change files owner */
            if ($apacheUser != "") {
                foreach (glob($Nagioscfg["cfg_dir"] . '/*.{json,cfg}', GLOB_BRACE) as $file) {
                    //handle path traversal vulnerability
                    if (str_contains($file, '..')) {
                        throw new Exception('Path traversal found');
                    }
                    if (posix_getuid() === 0) {
                        @chown($file, $apacheUser);
                        @chgrp($file, $apacheUser);
                    }
                }
                foreach (glob($Nagioscfg["cfg_dir"] . "/*.DEBUG") as $file) {
                    //handle path traversal vulnerability
                    if (str_contains($file, '..')) {
                        throw new Exception('Path traversal found');
                    }
                    if (posix_getuid() === 0) {
                        @chown($file, $apacheUser);
                        @chgrp($file, $apacheUser);
                    }
                }
            } else {
                print "Please check that files in the followings directory are writable by apache user : "
                    . $Nagioscfg["cfg_dir"] . "\n";
            }

            /*
             * Centreon Broker configuration
             */
            $listBrokerFile = glob($this->brokerCachePath . '/' . $host['id'] . "/*.{xml,json,cfg}", GLOB_BRACE);
            if (count($listBrokerFile) > 0) {
                $centreonBrokerDirCfg = getCentreonBrokerDirCfg($host['id']);
                if (!is_null($centreonBrokerDirCfg)) {
                    if (!is_dir($centreonBrokerDirCfg)) {
                        if (!mkdir($centreonBrokerDirCfg, 0755)) {
                            throw new Exception(
                                sprintf(
                                    _("Centreon Broker's configuration directory '%s' does not exist and could not be "
                                        . "created for monitoring engine '%s'. Please check it's path or create it"),
                                    $centreonBrokerDirCfg,
                                    $host['name']
                                )
                            );
                        }
                    }
                    foreach ($listBrokerFile as $fileCfg) {
                        $succeded = @copy($fileCfg, rtrim($centreonBrokerDirCfg, "/") . '/' . basename($fileCfg));
                        if (!$succeded) {
                            throw new Exception(
                                sprintf(
                                    _("Could not write to Centreon Broker's configuration file '%s' for monitoring "
                                        . "engine '%s'. Please add writing permissions for the webserver's user"),
                                    basename($fileCfg),
                                    $host['name']
                                )
                            );
                        }
                    }
                }

                /* Change files owner */
                if ($apacheUser != "") {
                    foreach (glob(rtrim($centreonBrokerDirCfg, "/") . "/" . "/*.{xml,json,cfg}", GLOB_BRACE) as $file) {
                        //handle path traversal vulnerability
                        if (str_contains($file, '..')) {
                            throw new Exception('Path traversal found');
                        }
                        @chown($file, $apacheUser);
                        @chgrp($file, $apacheUser);
                    }
                } else {
                    print "Please check that files in the followings directory are writable by apache user : "
                        . rtrim($centreonBrokerDirCfg, "/") . "/\n";
                }
            }

            if (strlen($msg_copy) == 0) {
                $msg_copy .= _("OK: All configuration files copied with success.");
            }
        } else {
            /*
             * Get Parent Remote Servers of the Poller
             */
            $statementRemotes = $pearDB->prepare(
                'SELECT ns.id
                FROM nagios_server AS ns
                JOIN platform_topology AS pt ON (ns.id = pt.server_id)
                WHERE ns.id = :pollerId
                AND pt.type = "remote"
                UNION
                SELECT ns1.id
                FROM nagios_server AS ns1
                JOIN platform_topology AS pt ON (ns1.id = pt.server_id)
                JOIN nagios_server AS ns2 ON ns1.id = ns2.remote_id
                WHERE ns2.id = :pollerId
                AND pt.type = "remote"
                UNION
                SELECT ns1.id
                FROM nagios_server AS ns1
                JOIN platform_topology AS pt ON (ns1.id = pt.server_id)
                JOIN rs_poller_relation AS rspr ON rspr.remote_server_id = ns1.id
                WHERE rspr.poller_server_id = :pollerId
                AND pt.type = "remote"'
            );
            $statementRemotes->bindValue(':pollerId', $pollerId, PDO::PARAM_INT);
            $statementRemotes->execute();
            $remotesResults = $statementRemotes->fetchAll(PDO::FETCH_ASSOC);

            /*
             * If the poller is linked to one or many remotes
             */
            foreach ($remotesResults as $remote) {
                $linkedStatement = $pearDB->prepare(
                    'SELECT id
                    FROM nagios_server
                    WHERE remote_id = :remoteId
                    UNION
                    SELECT poller_server_id AS id
                    FROM rs_poller_relation
                    WHERE remote_server_id = :remoteId'
                );
                $linkedStatement->bindValue(':remoteId', $remote['id'], PDO::PARAM_INT);
                $linkedStatement->execute();
                $linkedResults = $linkedStatement->fetchAll(PDO::FETCH_ASSOC);

                $exportParams = [
                    'server' => $remote['id'],
                    'pollers' => []
                ];

                $exportParams['pollers'] = !empty($linkedResults) ? array_column($linkedResults, 'id') : [$remote['id']];

                $this->dependencyInjector[ServiceProvider::CENTREON_TASKSERVICE]->addTask(
                    Task::TYPE_EXPORT,
                    ['params' => $exportParams]
                );
            }
            $return = $this->writeToCentcorePipe('SENDCFGFILE', $host["id"]);

            $msg_copy .= _(
                "OK: All configuration will be send to '"
                . $host['name'] . "' by centcore in several minutes."
            );
        }
        print $msg_copy . "\n";
        return $return;
    }

    /**
     * Get apache user to set file access
     *
     * @return string
     */
    public function getApacheUser()
    {
        /* Change files owner */
        $installFile = "/etc/centreon/instCentWeb.conf";

        if (file_exists($installFile)) {
            $stream = file_get_contents($installFile);
            $lines = preg_split("/\n/", $stream);
            foreach ($lines as $line) {
                if (preg_match('/WEB\_USER\=([a-zA-Z\_\-]*)/', $line, $tabUser)) {
                    if (isset($tabUser[1])) {
                        return $tabUser[1];
                    } else {
                        return "";
                    }
                }
            }
        }
        return "";
    }

    /**
     * Send Trap configuration files to poller
     *
     * @param int|null $pollerId
     *
     * @return int
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function sendTrapCfg($pollerId = null)
    {
        if (is_null($pollerId)) {
            throw new CentreonClapiException(self::MISSING_POLLER_ID);
        }
        $pollerId = $this->ensurePollerId($pollerId);

        $centreonDir = $this->centreon_path;
        $pearDB = $this->dependencyInjector['configuration_db'];
        $statement = $pearDB->prepare("SELECT snmp_trapd_path_conf FROM nagios_server WHERE id = :pollerId");
        $statement->bindValue(':pollerId', $pollerId, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetchRow();
        $trapdPath = $row['snmp_trapd_path_conf'];
        if (!is_dir("{$trapdPath}/{$pollerId}")) {
            mkdir("{$trapdPath}/{$pollerId}");
        }
        $filename = "{$trapdPath}/{$pollerId}/centreontrapd.sdb";
        //handle path traversal vulnerability
        if (str_contains($filename, '..')) {
            throw new Exception('Path traversal found');
        }
        $cmd = sprintf('%s %d %s 2>&1',
                       escapeshellarg($centreonDir . '/bin/generateSqlLite'),
                       $pollerId,
                       escapeshellarg($filename));
        passthru($cmd);
        $return = $this->writeToCentcorePipe('SYNCTRAP', $pollerId);
        return $return;
    }

    /**
     *
     * Display Copying files
     *
     * @param string|null $filename
     * @param string|null $status
     *
     * @return string|void
     */
    private function displayCopyingFile($filename = null, $status = null)
    {
        if (!isset($filename)) {
            return;
        }
        $str = "- " . $filename . " -> " . $status . "\n";
        return $str;
    }

    /**
     * @return array
     * @throws PDOException
     */
    public function getPollerState()
    {
        $pollerState = [];
        $dbResult = $this->DBC->query("SELECT instance_id, running, name FROM instances");

        while ($row = $dbResult->fetchRow()) {
            $pollerState[$row['instance_id']] = $row['running'];
        }
        return $pollerState;
    }
}
