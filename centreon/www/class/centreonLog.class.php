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

use Psr\Log\LogLevel;

/**
 * Class
 *
 * @class CentreonUserLog
 */
class CentreonUserLog
{
    public const TYPE_LOGIN = 1;
    public const TYPE_SQL = 2;
    public const TYPE_LDAP = 3;
    public const TYPE_UPGRADE = 4;

    /** @var CentreonUserLog */
    private static $instance;
    /** @var array */
    private $errorType;
    /** @var int */
    private $uid;
    /** @var string */
    private $path;

    /**
     * CentreonUserLog constructor
     *
     * @param int $uid
     * @param CentreonDB $pearDB
     */
    public function __construct($uid, $pearDB)
    {
        $this->uid = $uid;
        $this->errorType = array();

        // Get Log directory path
        $DBRESULT = $pearDB->query("SELECT * FROM `options` WHERE `key` = 'debug_path'");
        while ($res = $DBRESULT->fetchRow()) {
            $optGen[$res["key"]] = $res["value"];
        }
        $DBRESULT->closeCursor();

        // Init log Directory
        $this->path = (isset($optGen["debug_path"]) && !empty($optGen["debug_path"])) ?
            $optGen["debug_path"] : _CENTREON_LOG_;

        $this->errorType[self::TYPE_LOGIN] = $this->path . "/login.log";
        $this->errorType[self::TYPE_SQL] = $this->path . "/sql-error.log";
        $this->errorType[self::TYPE_LDAP] = $this->path . "/ldap.log";
        $this->errorType[self::TYPE_UPGRADE] = $this->path . "/upgrade.log";
    }

    /**
     * @param int $id
     * @param string $str
     * @param int $print
     * @param int $page
     * @param int $option
     * @return void
     */
    public function insertLog($id, $str, $print = 0, $page = 0, $option = 0): void
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

    /**
     * @param int $uid
     * @return void
     */
    public function setUID($uid): void
    {
        $this->uid = $uid;
    }

    /**
     * Singleton
     *
     * @param int $uid The user id
     * @return CentreonUserLog
     * @throws Exception
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
     * Level Types from \Psr\Log\LogLevel
     */
    public const LEVEL_DEBUG = LogLevel::DEBUG;
    public const LEVEL_NOTICE = LogLevel::NOTICE;
    public const LEVEL_INFO = LogLevel::INFO;
    public const LEVEL_WARNING = LogLevel::WARNING;
    public const LEVEL_ERROR = LogLevel::ERROR;
    public const LEVEL_CRITICAL = LogLevel::CRITICAL;
    public const LEVEL_ALERT = LogLevel::ALERT;
    public const LEVEL_EMERGENCY = LogLevel::EMERGENCY;

    /**
     * Log files
     */
    public const TYPE_LOGIN = 1;
    public const TYPE_SQL = 2;
    public const TYPE_LDAP = 3;
    public const TYPE_UPGRADE = 4;
    public const TYPE_PLUGIN_PACK_MANAGER = 5;
    public const TYPE_BUSINESS_LOG = 6;


    private const DEFAULT_LOG_FILES = [
        self::TYPE_LOGIN => 'login.log',
        self::TYPE_SQL => 'sql-error.log',
        self::TYPE_LDAP => 'ldap.log',
        self::TYPE_UPGRADE => 'upgrade.log',
        self::TYPE_PLUGIN_PACK_MANAGER => 'plugin-pack-manager.log',
    ];

    /** @var array<int,string> */
    private array $logFileHandler;

    /** @var string */
    private string $pathLogFile;

    /**
     * CentreonLog constructor
     *
     * @param array $customLogFiles
     * @param string $pathLogFile
     */
    public function __construct($customLogFiles = [], string $pathLogFile = '')
    {
        $this->setPathLogFile(empty($pathLogFile) ? _CENTREON_LOG_ : $pathLogFile);
        // push default logs in log file handler
        foreach (self::DEFAULT_LOG_FILES as $logTypeId => $logFileName) {
            $this->pushLogFileHandler($logTypeId, $logFileName);
        }
        // push custom logs in log file handler
        foreach ($customLogFiles as $logTypeId => $logFileName) {
            $this->pushLogFileHandler($logTypeId, $logFileName);
        }
    }

    /**
     * Factory
     * @param array $customLogs
     * @param string $pathLogFile
     * @return CentreonLog
     */
    public static function create(array $customLogs = [], string $pathLogFile = ''): CentreonLog
    {
        return new CentreonLog($customLogs, $pathLogFile);
    }

    /**
     * @param int $logTypeId TYPE_* constants
     * @param string $level LEVEL_* constants
     * @param string $message
     * @param array $customContext
     * @param Throwable|null $exception
     * @return void
     */
    public function log(
        int $logTypeId,
        string $level,
        string $message,
        array $customContext = [],
        ?Throwable $exception = null
    ): void {
        if (! empty($message)) {
            $jsonContext = $this->serializeContext($customContext, $exception);
            $level = (empty($level)) ? strtoupper(self::LEVEL_ERROR) : strtoupper($level);
            $date = (new DateTime())->format(DateTimeInterface::RFC3339);
            $log = sprintf("[%s] %s : %s | %s", $date, $level, $message, $jsonContext);
            $response = file_put_contents($this->logFileHandler[$logTypeId], $log . "\n", FILE_APPEND);
        }
    }

    /**
     * @param int $logTypeId TYPE_* constants
     * @param string $message
     * @param array $customContext
     * @param Throwable|null $exception
     * @return void
     */
    public function debug(int $logTypeId, string $message, array $customContext = [], ?Throwable $exception = null): void
    {
        $this->log($logTypeId, self::LEVEL_DEBUG, $message, $customContext, $exception);
    }

    /**
     * @param int $logTypeId TYPE_* constants
     * @param string $message
     * @param array $customContext
     * @param Throwable|null $exception
     * @return void
     */
    public function notice(int $logTypeId, string $message, array $customContext = [], ?Throwable $exception = null): void
    {
        $this->log($logTypeId, self::LEVEL_NOTICE, $message, $customContext, $exception);
    }

    /**
     * @param int $logTypeId TYPE_ * constants
     * @param string $message
     * @param array $customContext
     * @param Throwable|null $exception
     * @return void
     */
    public function info(int $logTypeId, string $message, array $customContext = [], ?Throwable $exception = null): void
    {
        $this->log($logTypeId, self::LEVEL_INFO, $message, $customContext, $exception);
    }

    /**
     * @param int $logTypeId TYPE_* constants
     * @param string $message
     * @param array $customContext
     * @param Throwable|null $exception
     * @return void
     */
    public function warning(int $logTypeId, string $message, array $customContext = [], ?Throwable $exception = null): void
    {
        $this->log($logTypeId, self::LEVEL_WARNING, $message, $customContext, $exception);
    }

    /**
     * @param int $logTypeId TYPE_* constants
     * @param string $message
     * @param array $customContext
     * @param Throwable|null $exception
     * @return void
     */
    public function error(int $logTypeId, string $message, array $customContext = [], ?Throwable $exception = null): void
    {
        $this->log($logTypeId, self::LEVEL_ERROR, $message, $customContext, $exception);
    }

    /**
     * @param int $logTypeId TYPE_* constants
     * @param string $message
     * @param array $customContext
     * @param Throwable|null $exception
     * @return void
     */
    public function critical(int $logTypeId, string $message, array $customContext = [], ?Throwable $exception = null): void
    {
        $this->log($logTypeId, self::LEVEL_CRITICAL, $message, $customContext, $exception);
    }

    /**
     * @param int $logTypeId TYPE_* constants
     * @param string $message
     * @param array $customContext
     * @param Throwable|null $exception
     * @return void
     */
    public function alert(int $logTypeId, string $message, array $customContext = [], ?Throwable $exception = null): void
    {
        $this->log($logTypeId, self::LEVEL_ALERT, $message, $customContext, $exception);
    }

    /**
     * @param int $logTypeId TYPE_* constants
     * @param string $message
     * @param array $customContext
     * @param Throwable|null $exception
     * @return void
     */
    public function emergency(int $logTypeId, string $message, array $customContext = [], ?Throwable $exception = null): void
    {
        $this->log($logTypeId, self::LEVEL_EMERGENCY, $message, $customContext, $exception);
    }

    /**
     * @return array
     */
    public function getLogFileHandler(): array
    {
        return $this->logFileHandler;
    }

    /**
     * @param int $logTypeId
     * @param string $logFileName
     * @return CentreonLog
     */
    public function pushLogFileHandler(int $logTypeId, string $logFileName): CentreonLog
    {
        $pathLogFileName = '';
        $logFile = '';
        $explodeFileName = explode(DIRECTORY_SEPARATOR, $logFileName);
        if (! empty($explodeFileName)) {
            $logFile = $explodeFileName[count($explodeFileName) - 1];
            unset($explodeFileName[count($explodeFileName) - 1]);
            $pathLogFileName = implode(DIRECTORY_SEPARATOR, $explodeFileName);
        }
        $this->logFileHandler[$logTypeId] = ($pathLogFileName !== $this->pathLogFile) ?
            $this->pathLogFile . '/' . $logFile : $logFileName;
        return $this;
    }

    /**
     * @param string $pathLogFile
     * @return CentreonLog
     */
    public function setPathLogFile(string $pathLogFile): CentreonLog
    {
        $this->pathLogFile = $pathLogFile;
        return $this;
    }

    /**
     * @param array $customContext
     * @param Throwable|null $exception
     * @return string
     */
    private function serializeContext(array $customContext, ?Throwable $exception = null): string
    {
        try {
            $exceptionContext = [];

            // Add default context with back trace and request infos
            $defaultContext = [
                'back_trace' => $this->getBackTrace(),
                'request_infos' => [
                    'url' => $_SERVER['REQUEST_URI'] ?? null,
                    'http_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                    'server' => $_SERVER['SERVER_NAME'] ?? null,
                    'referrer' => $_SERVER['HTTP_REFERER'] ?? null
                ]
            ];

            // Add exception context and if possible the previous
            if (! is_null($exception)) {
                $exceptionContext = $this->getExceptionInfos($exception);
                $exceptionContext['previous'] = ! is_null($exception->getPrevious()) ?
                    $this->getExceptionInfos($exception) : null;
            }

            $context = [
                'context' => [
                    'default' => $defaultContext,
                    'exception' => ! empty($exceptionContext) ? $exceptionContext : null,
                    'custom' => ! empty($customContext) ? $customContext : null,
                ]
            ];

            return json_encode(
                $context,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $e) {
            return sprintf(
                "context: error while json encoding (JsonException: %s)",
                $e->getMessage()
            );
        }
    }

    /**
     * @param Throwable $exception
     * @return array
     */
    private function getExceptionInfos(Throwable $exception): array
    {
        $exceptionInfos = [
            'exception_type' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'message' => $exception->getMessage()
        ];
        $additonalOptions = $this->getExceptionOptions($exception);
        if (! empty($additonalOptions)) {
            $exceptionInfos['options'] = $additonalOptions;
        }
        return $exceptionInfos;
    }

    /**
     * @param Throwable $exception
     * @return array
     */
    private function getExceptionOptions(Throwable $exception): array
    {
        return (method_exists($exception, 'getOptions') && is_array($exception->getOptions())) ?
            $exception->getOptions() : [];
    }

    /**
     * @return array|null
     */
    private function getBackTrace(): ?array
    {
        $excludeFunctions = ['log', 'debug', 'info', 'warning', 'error', 'critical', 'alert', 'emergency', 'insertLog'];
        $backTrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        if (empty($backTrace)) {
            return null;
        }
        // get the last trace excluding the centreonlog trace
        $lastTraceCleaned = array_values(
            array_filter(
                $backTrace,
                fn(array $trace): bool => isset($trace['file']) && ! str_contains(
                        $trace['file'],
                        'centreonLog.class.php'
                    )
            )
        );

        if (empty($lastTraceCleaned)) {
            return null;
        }

        return [
            'file' => $lastTraceCleaned[0]['file'] ?? null,
            'line' => $lastTraceCleaned[0]['line'] ?? null,
            'class' => (isset($lastTraceCleaned[0]['class']) && $lastTraceCleaned[0]['class'] !== 'CentreonLog') ? $lastTraceCleaned[0]['class'] : null,
            'function' => (isset($trace[0]['function']) && ! in_array(
                    $lastTraceCleaned[0]['function'],
                    $excludeFunctions,
                    true
                )) ? $lastTraceCleaned[0]['function'] : null
        ];
    }

    //*********************************************** DEPRECATED *****************************************************//

    /**
     * @param int $id
     * @param string $str
     * @param int $print
     * @param int $page
     * @param int $option
     * @return void
     * @deprecated Instead used {@see CentreonLog::log()}
     */
    public function insertLog($id, $str, $print = 0, $page = 0, $option = 0): void
    {
        $message = "$page|$option|$str";

        if ($print) {
            print $str;
        }

        $this->log(logTypeId: $id, level: self::LEVEL_ERROR, message: $message);
    }


}
