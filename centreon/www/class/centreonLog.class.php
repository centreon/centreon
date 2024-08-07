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
 * @class CentreonUserLog
 */
class CentreonUserLog
{
    private static $instance;
    private $errorType;
    private $uid;
    private $path;

    public const TYPE_LOGIN = 1;
    public const TYPE_SQL = 2;
    public const TYPE_LDAP = 3;
    public const TYPE_UPGRADE = 4;

    /*
     * Constructor
     */
    public function __construct($uid, $pearDB)
    {

        $this->uid = $uid;
        $this->errorType = array();

        /*
         * Get Log directory path
         */
        $DBRESULT = $pearDB->query("SELECT * FROM `options` WHERE `key` = 'debug_path'");
        while ($res = $DBRESULT->fetchRow()) {
            $optGen[$res["key"]] = $res["value"];
        }
        $DBRESULT->closeCursor();

        /*
         * Init log Directory
         */
        if (isset($optGen["debug_path"]) && $optGen["debug_path"] != "") {
            $this->path = $optGen["debug_path"];
        } else {
            $this->path = _CENTREON_LOG_;
        }

        $this->errorType[self::TYPE_LOGIN] = $this->path . "/login.log";
        $this->errorType[self::TYPE_SQL] = $this->path . "/sql-error.log";
        $this->errorType[self::TYPE_LDAP] = $this->path . "/ldap.log";
        $this->errorType[self::TYPE_UPGRADE] = $this->path . "/upgrade.log";
    }

    /*
     * Function for writing logs
     */

    public function insertLog($id, $str, $print = 0, $page = 0, $option = 0)
    {
        /*
         * Construct alert message
         * Take care before modifying this message pattern as it may break tools such as fail2ban
         */
        $string = date("Y-m-d H:i:s") . "|" . $this->uid . "|$page|$option|$str";

        /*
         * Display error on Standard exit
         */
        if ($print) {
            print htmlspecialchars($str);
        }

        /*
         * Replace special char
         */
        $string = str_replace("`", "", $string);
        $string = str_replace("*", "\*", $string);

        /*
         * Write Error in log file.
         */
        file_put_contents($this->errorType[$id], $string . "\n", FILE_APPEND);
    }

    public function setUID($uid)
    {
        $this->uid = $uid;
    }

    /**
     * Singleton
     *
     * @param int $uid The user id
     * @return CentreonUserLog
     */
    public static function singleton($uid = 0)
    {
        if (is_null(self::$instance)) {
            self::$instance = new CentreonUserLog($uid, CentreonDB::factory('centreon'));
        }
        return self::$instance;
    }
}

/**
 * Class
 *
 * @class CentreonLog
 */
class CentreonLog
{
    /**
     * Level type
     */
    public const LEVEL_INFOS = "info";
    public const LEVEL_NOTICE = "notice";
    public const LEVEL_DEBUG = "debug";
    public const LEVEL_WARNING = "warning";
    public const LEVEL_ERROR = "error";
    public const LEVEL_CRITICAL = "critical";
    public const LEVEL_ALERT = "alert";
    public const LEVEL_EMERGENCY = "emergency";

    /**
     * Log file
     */
    public const TYPE_LOGIN = 1;
    public const TYPE_SQL = 2;
    public const TYPE_LDAP = 3;
    public const TYPE_UPGRADE = 4;
    public const TYPE_PLUGIN_PACK_MANAGER = 5;

    /** @var array */
    private $errorType;

    /** @var string */
    private $path;

    /**
     * CentreonLog constructor
     *
     * @param array $customLogs
     */
    public function __construct($customLogs = [])
    {
        $this->errorType = array();

        $this->path = _CENTREON_LOG_;

        $this->errorType[1] = $this->path . "/login.log";
        $this->errorType[2] = $this->path . "/sql-error.log";
        $this->errorType[3] = $this->path . "/ldap.log";
        $this->errorType[4] = $this->path . "/upgrade.log";
        $this->errorType[5] = $this->path . '/plugin-pack-manager.log';

        foreach ($customLogs as $key => $value) {
            if (!preg_match('@' . $this->path . '@', $value)) {
                $value = $this->path . '/' . $value;
            }
            $this->errorType[$key] = $value;
        }
    }

    /**
     * Factory
     * @param array $customLogs
     * @return CentreonLog
     */
    public static function create(array $customLogs = []): CentreonLog
    {
        return new CentreonLog($customLogs);
    }

    /**
     * @param int $type
     * @param string $level
     * @param string $message
     * @param array $customContext
     * @return void
     */
    public function log(int $type, string $level, string $message, array $customContext): void
    {
        if (! empty($message)) {
            $jsonContext = $this->prepareContext($customContext, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1));
            $level = (empty($level)) ? strtoupper(self::LEVEL_ERROR) : strtoupper($level);
            $date = (new DateTime())->format(DateTimeInterface::RFC3339);
            $log = "[$date] $level : $message | $jsonContext";
            file_put_contents($this->errorType[$type], $log . "\n", FILE_APPEND);
        }
    }

    /**
     * @param int $type
     * @param string $message
     * @param array $customContext
     * @return void
     */
    public function debug(int $type, string $message, array $customContext): void
    {
        $this->log($type, self::LEVEL_DEBUG, $message, $customContext);
    }

    /**
     * @param int $type
     * @param string $message
     * @param array $customContext
     * @return void
     */
    public function info(int $type, string $message, array $customContext): void
    {
        $this->log($type, self::LEVEL_INFOS, $message, $customContext);
    }

    /**
     * @param int $type
     * @param string $message
     * @param array $customContext
     * @return void
     */
    public function warning(int $type, string $message, array $customContext): void
    {
        $this->log($type, self::LEVEL_WARNING, $message, $customContext);
    }

    /**
     * @param int $type
     * @param string $message
     * @param array $customContext
     * @return void
     */
    public function error(int $type, string $message, array $customContext): void
    {
        $this->log($type, self::LEVEL_ERROR, $message, $customContext);
    }

    /**
     * @param int $type
     * @param string $message
     * @param array $customContext
     * @return void
     */
    public function critical(int $type, string $message, array $customContext): void
    {
        $this->log($type, self::LEVEL_CRITICAL, $message, $customContext);
    }

    /**
     * @param int $type
     * @param string $message
     * @param array $customContext
     * @return void
     */
    public function alert(int $type, string $message, array $customContext): void
    {
        $this->log($type, self::LEVEL_ALERT, $message, $customContext);
    }

    /**
     * @param int $type
     * @param string $message
     * @param array $customContext
     * @return void
     */
    public function emergency(int $type, string $message, array $customContext): void
    {
        $this->log($type, self::LEVEL_EMERGENCY, $message, $customContext);
    }

    /**
     * @param array $customContext
     * @param array $trace
     * @return string
     */
    private function prepareContext(array $customContext, array $trace): string
    {
        try {
            $excludeFunctions = ['log', 'prepareContext', 'insertLog'];
            $defaultContext = [
                'file' => null,
                'line' => null,
                'class' => null,
                'function' => null,
                'request_infos' => [
                    'url' => $_SERVER['REQUEST_URI'] ?? null,
                    'http_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                    'server' => $_SERVER['SERVER_NAME'] ?? null,
                    'referrer' => $_SERVER['HTTP_REFERER'] ?? null
                ]
            ];
            if (! empty($trace)) {
                $defaultContext['file'] = $trace[0]['file'] ?? null;
                $defaultContext['line'] = $trace[0]['line'] ?? null;
                $defaultContext['class'] = (isset($trace[0]['class']) && $trace[0]['class'] !== 'CentreonLog') ? $trace[0]['class'] : null;
                $defaultContext['function'] = (isset($trace[0]['function']) && ! in_array(
                        $trace[0]['function'],
                        $excludeFunctions,
                        true
                    )) ? $trace[0]['function'] : null;
            }
            $context = [
                'context' => [
                    'default' => $defaultContext,
                    'custom' => $customContext
                ]
            ];
            return json_encode(
                $context,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $e) {
            return "context: error while json encoding (JsonException: {$e->getMessage()})";
        }
    }

    //*********************************************** DEPRECATED *****************************************************//

    /**
     * @param int $id
     * @param $str
     * @param int $print
     * @param int $page
     * @param int $option
     * @param string $level
     * @param array $customContext
     * @return void
     * @deprecated Instead used {@see CentreonLog::log()}
     */
    public function insertLog($id, $str, $print = 0, $page = 0, $option = 0, string $level = '', array $customContext = [])
    {
        $message = "$page|$option|$str";

        if ($print) {
            print $str;
        }

        $this->log(type: $id, level: $level, message: $message, customContext: $customContext);
    }
}
