<?php

/*
 * Copyright 2005-2022 Centreon
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

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../class/centreonAuth.class.php';

use Symfony\Component\Yaml\Yaml;
use Centreon\Domain\VersionHelper;

/**
 * Checks if line is sql comment
 *
 * @param string $str
 * @return bool
 */
function isSqlComment($str)
{
    if (str_starts_with(trim($str), "--")) {
        return true;
    }
    return false;
}

/**
 * Get template
 *
 * @param string $dir directory of templates
 * @return Smarty
 */
function getTemplate($dir)
{
    $libDir = __DIR__ . '/../../../GPL_LIB';

    $template = new \SmartyBC();
    $template->setTemplateDir($dir);
    $template->setCompileDir($libDir . '/SmartyCache/compile');
    $template->setConfigDir($libDir . '/SmartyCache/config');
    $template->setCacheDir($libDir . '/SmartyCache/cache');
    $template->addPluginsDir($libDir . '/smarty-plugins');
    $template->loadPlugin('smarty_function_eval');
    $template->setForceCompile(true);
    $template->setAutoLiteral(false);

    return $template;
}

/**
 * Connect to database with user root
 *
 * @return mixed
 */
function myConnect()
{
    $user = "root";
    if (!empty($_SESSION['root_user'])) {
        $user = $_SESSION['root_user'];
    }
    $pass = "";
    if (!empty($_SESSION['root_password'])) {
        $pass = $_SESSION['root_password'];
    }
    $host = "localhost";
    if (isset($_SESSION['ADDRESS']) && $_SESSION['ADDRESS']) {
        $host = $_SESSION['ADDRESS'];
    }
    $port = "3306";
    if (isset($_SESSION['DB_PORT']) && $_SESSION['DB_PORT']) {
        $port = $_SESSION['DB_PORT'];
    }
    return new \PDO('mysql:host=' . $host . ';port=' . $port, $user, $pass);
}

/**
 * Replace macros
 *
 * @param string $query
 * @return string
 */
function replaceInstallationMacros($query, $macros = [])
{
    while (preg_match('/@([a-zA-Z0-9_]+)@/', $query, $matches)) {
        $macroValue = "";
        if ($matches[1] == 'MAILER') {
            $macroValue = '-MAILER-';
        } elseif (isset($macros[$matches[1]])) {
            $macroValue = $macros[$matches[1]];
        } elseif (isset($_SESSION[$matches[1]])) {
            $macroValue = $_SESSION[$matches[1]];
        }

        $query = preg_replace('/@' . $matches[1] . '@/', $macroValue, $query);
    }

    $query = str_replace('-MAILER-', '@MAILER@', $query);

    return $query;
}

/**
 * Split queries
 *
 * @param string $file
 * @param string $delimiter
 * @param CentreonDB $connector
 * @param string $tmpFile | $tmpFile will store the number of executed queries sql script
 * @return string | returns "0" if everything is ok, or returns error message
 */
function splitQueries($file, $delimiter = ';', $connector = null, $tmpFile = "", $macros = [])
{
    if (is_null($connector)) {
        $connector = myConnect();
    }

    set_time_limit(0);
    $count = 0;
    $start = 0;
    $fileName = basename($file);
    if ($tmpFile != '' && is_file($tmpFile)) {
        $start = file_get_contents($tmpFile);
    }
    if (is_file($file) === true) {
        $file = fopen($file, 'r');
        if (is_resource($file) === true) {
            $query = [];
            $line = 0;
            while (feof($file) === false) {
                $line++;
                $currentLine = fgets($file);
                if (false == isSqlComment($currentLine)) {
                    $query[] = $currentLine;
                }
                if (preg_match('~' . preg_quote($delimiter, '~') . '\s*$~iS', end($query)) === 1) {
                    $query = trim(implode('', $query));
                    $query = replaceInstallationMacros($query, $macros);
                    $count++;
                    if ($count > $start) {
                        try {
                            $result = $connector->query($query);
                            if (!$result) {
                                throw new \Exception('Cannot execute query : ' . $query);
                            }
                        } catch (\Exception $e) {
                            return "$fileName Line $line:" . $e->getMessage();
                        }
                        while (ob_get_level() > 0) {
                            ob_end_flush();
                        }
                        flush();
                        if ($tmpFile != '') {
                            file_put_contents($tmpFile, $count);
                        }
                    }
                }
                if (is_string($query) === true) {
                    $query = [];
                }
            }
            fclose($file);
            return "0";
        }
    }
    return _('File not found');
}

/**
 * Exit process
 *
 * @param string $id | name of the process
 * @param int $result | 0 = ok, 1 = nok
 * @param string $msg | error message
 */
function exitProcess($id, $result, $msg)
{
    $msg = str_replace('"', '\"', $msg);
    $msg = str_replace('\\', '\\\\', $msg);

    echo '{
        "id" : "' . $id . '",
        "result" : "' . $result . '",
        "msg" : "' . $msg . '"
        }';

    exit;
}

/**
 * Exit upgrade process
 *
 * @param int $result | 0 = ok, 1 = nok
 * @param string $current
 * @param string $next
 * @param string $msg | error message
 * @return void
 */
function exitUpgradeProcess($result, $current, $next, $msg)
{
    $msg = str_replace('"', '\"', $msg);
    $msg = str_replace('\\', '\\\\', $msg);
    echo '{
        "result" : "' . $result . '",
        "current" : "' . $current . '",
        "next" : "' . $next . '",
        "msg" : "' . $msg . '"
        }';
    exit;
}

/**
 * Get param lines from file
 *
 * @param string $varPath
 * @param string $objectType
 * @return array
 */
function getParamLines($varPath, $objectType)
{
    $contents = "";
    if ($handle = opendir($varPath)) {
        while (false !== ($object = readdir($handle))) {
            if ($object == $objectType) {
                $contents = file_get_contents($varPath . '/' . $object);
            }
        }
        closedir($handle);
    }
    $lines = explode("\n", $contents);
    return $lines;
}

/**
 * Set session variables
 *
 * @param array $conf_centreon
 * @return void
 */
function setSessionVariables($conf_centreon)
{
    $_SESSION['INSTALL_DIR_CENTREON'] = $conf_centreon['centreon_dir'];
    $_SESSION['CENTREON_ETC'] = $conf_centreon['centreon_etc'];
    $_SESSION['BIN_MAIL'] = $conf_centreon['mail'];
    $_SESSION['BIN_RRDTOOL'] = $conf_centreon['rrdtool_dir'];
    $_SESSION['MONITORINGENGINE_USER'] = $conf_centreon['monitoring_user'];
    $_SESSION['MONITORINGENGINE_GROUP'] = $conf_centreon['monitoring_group'];
    $_SESSION['MONITORINGENGINE_ETC'] = $conf_centreon['monitoring_etc'];
    $_SESSION['MONITORINGENGINE_PLUGIN'] = $conf_centreon['plugin_dir'];
    $_SESSION['CENTREON_LOG'] = $conf_centreon['centreon_log'];
    $_SESSION['CENTREON_RRD_DIR'] = $conf_centreon['centreon_dir_rrd'];
    $_SESSION['MONITORING_BINARY'] = $conf_centreon['monitoring_binary'];
    $_SESSION['CENTREON_VARLIB'] = $conf_centreon['centreon_varlib'];
    $_SESSION['MONITORING_VAR_LOG'] = $conf_centreon['monitoring_varlog'];
    $_SESSION['CENTREON_ENGINE_CONNECTORS'] = $conf_centreon['centreon_engine_connectors'];
    $_SESSION['CENTREON_ENGINE_LIB'] = $conf_centreon['centreon_engine_lib'];
    $_SESSION['CENTREONPLUGINS'] = $conf_centreon['centreon_plugins'];
}

function getDatabaseVariable($db, $variable)
{
    $query = "SHOW VARIABLES LIKE '" . $variable . "'";
    $result = $db->query($query);

    $value = null;
    while ($row = $result->fetch()) {
        $value = $row['Value'];
    }
    $result->closeCursor();

    return $value;
}

/**
 * Get gorgone api credentials from configuration file
 *
 * @param string $gorgoneEtcPath
 * @return array{
 *     GORGONE_USER: string
 *     GORGONE_PASSWORD: string
 * }
 */
function getGorgoneApiCredentialMacros(string $gorgoneEtcPath): array
{
    $macros = [
        'GORGONE_USER' => 'centreon-gorgone',
        'GORGONE_PASSWORD' => '',
    ];

    $apiConfigurationFile = $gorgoneEtcPath . '/config.d/31-centreon-api.yaml';
    if (file_exists($apiConfigurationFile)) {
        $configuration = Yaml::parseFile($apiConfigurationFile);

        if (isset($configuration['gorgone']['tpapi'][0]['username'])) {
            $macros['GORGONE_USER'] = $configuration['gorgone']['tpapi'][0]['username'];
        }

        if (isset($configuration['gorgone']['tpapi'][0]['password'])) {
            $macros['GORGONE_PASSWORD'] = password_hash(
                $configuration['gorgone']['tpapi'][0]['password'],
                CentreonAuth::PASSWORD_HASH_ALGORITHM
            );
        }
    }

    return $macros;
}

/**
 * Check PHP version and throws exception if prerequisite is not respected
 *
 * @param \PDO $db
 * @throws \Exception
 */
function checkPhpPrerequisite(): void
{
    $currentPhpMajorVersion = VersionHelper::regularizeDepthVersion(PHP_VERSION, 1);

    if (! VersionHelper::compare($currentPhpMajorVersion, _CENTREON_PHP_VERSION_, VersionHelper::EQUAL)) {
        throw new \Exception(
            sprintf(
                _('Please install PHP version %s instead of %s.'),
                _CENTREON_PHP_VERSION_,
                PHP_VERSION,
            ),
        );
    }
}

/**
 * Check MariaDB version and throws exception if prerequisite is not respected
 *
 * @param \PDO $db
 * @throws \Exception
 */
function checkMariaDBPrerequisite(\PDO $db): void
{
    $currentMariaDBVersion = getMariaDBVersion($db);

    if ($currentMariaDBVersion !== null) {
        $currentMariaDBMajorVersion = VersionHelper::regularizeDepthVersion($currentMariaDBVersion, 1);
        if (VersionHelper::compare($currentMariaDBMajorVersion, _CENTREON_MARIA_DB_MIN_VERSION_, VersionHelper::LT)) {
            throw new \Exception(
                sprintf(
                    _('Please install MariaDB version %s instead of %s.'),
                    _CENTREON_MARIA_DB_MIN_VERSION_,
                    $currentMariaDBVersion,
                ),
            );
        }
    }
}

/**
 * Get MariaDB version
 * Returns nulls if not found or if MySQL is installed
 *
 * @return string|null
 */
function getMariaDBVersion(\PDO $db): ?string
{
    $version = null;
    $dbmsName = null;

    $statement = $db->query("SHOW VARIABLES WHERE Variable_name IN ('version', 'version_comment')");
    while ($row = $statement->fetch()) {
        if ($row['Variable_name'] === "version") {
            $version = $row['Value'];
        } elseif ($row['Variable_name'] === "version_comment") {
            $dbmsName = $row['Value'];
        }
    }

    if (str_contains($dbmsName, "MariaDB") && $version !== null) {
        return $version;
    }

    return null;
}
