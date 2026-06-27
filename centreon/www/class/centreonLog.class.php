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

use Adaptation\Log\Enum\LogChannelEnum;
use Adaptation\Log\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * @deprecated use {@see \Adaptation\Log\Logger::create()}
 */
class CentreonUserLog
{
    public const TYPE_LOGIN = 1;
    public const TYPE_SQL = 2;
    public const TYPE_LDAP = 3;
    public const TYPE_UPGRADE = 4;

    /** @var CentreonUserLog */
    private static $instance;

    /** @var int */
    private $uid;

    /**
     * @param int $uid
     * @param CentreonDB $pearDB unused, kept for BC
     */
    public function __construct($uid, $pearDB)
    {
        $this->uid = $uid;
    }

    /**
     * @param int $id one of the TYPE_* constants
     * @param string $str
     * @param int $print
     * @param int $page
     * @param int $option
     */
    public function insertLog($id, $str, $print = 0, $page = 0, $option = 0): void
    {
        if ($print) {
            echo htmlspecialchars($str);
        }

        $message = str_replace(['`', '*'], ['', '\*'], (string) $str);

        $context = [
            'uid' => $this->uid,
            'page' => $page,
            'option' => $option,
        ];

        Logger::create(self::resolveChannel((int) $id))->info($message, $context);

        if ((int) $id === self::TYPE_LOGIN) {
            $this->mirrorAuthenticationEventToLegacyFile((string) $str, $page, $option);
        }
    }

    /**
     * @param int $uid
     */
    public function setUID($uid): void
    {
        $this->uid = $uid;
    }

    /**
     * Singleton
     *
     * @param int $uid The user id
     *
     * @throws Exception
     */
    public static function singleton($uid = 0): CentreonUserLog
    {
        if (! self::$instance instanceof self) {
            self::$instance = new CentreonUserLog($uid, null);
        }

        return self::$instance;
    }

    /**
     * Mirror an authentication event to the historical login.log file.
     *
     * Authentication events are now routed to the Monolog "authentication" channel
     * (prod.access.log). This duplicate write keeps the legacy pipe-delimited format and
     * file path so external consumers that watch /var/log/centreon/login.log (fail2ban
     * jails matching the "Authentication failed" line with the client IP, SIEM parsers)
     * keep working unchanged. It is transitional and will be removed in a future release
     * once those consumers read the Monolog access log instead.
     *
     * @param string $str the raw message as passed by the caller
     * @param int $page
     * @param int $option
     */
    private function mirrorAuthenticationEventToLegacyFile(string $str, $page, $option): void
    {
        $logDir = defined('_CENTREON_LOG_') ? _CENTREON_LOG_ : '/var/log/centreon';
        $line = date('Y-m-d H:i:s') . '|' . $this->uid . "|{$page}|{$option}|{$str}";
        $line = str_replace(['`', '*'], ['', '\*'], $line);

        try {
            $written = file_put_contents($logDir . '/login.log', $line . "\n", FILE_APPEND | LOCK_EX);
            if ($written === false) {
                error_log(sprintf('CentreonUserLog: unable to mirror authentication event to %s/login.log', $logDir));
            }
        } catch (\Throwable $e) {
            error_log(sprintf('CentreonUserLog: unable to mirror authentication event to login.log: %s', $e->getMessage()));
        }
    }

    private static function resolveChannel(int $type): LogChannelEnum
    {
        return match ($type) {
            self::TYPE_LOGIN, self::TYPE_LDAP => LogChannelEnum::AUTHENTICATION,
            self::TYPE_UPGRADE => LogChannelEnum::UPGRADE,
            default => LogChannelEnum::WEB,
        };
    }
}

/**
 * @deprecated use {@see \Adaptation\Log\Logger::create()}
 */
class CentreonLog
{
    public const LEVEL_DEBUG = LogLevel::DEBUG;
    public const LEVEL_NOTICE = LogLevel::NOTICE;
    public const LEVEL_INFO = LogLevel::INFO;
    public const LEVEL_WARNING = LogLevel::WARNING;
    public const LEVEL_ERROR = LogLevel::ERROR;
    public const LEVEL_CRITICAL = LogLevel::CRITICAL;
    public const LEVEL_ALERT = LogLevel::ALERT;
    public const LEVEL_EMERGENCY = LogLevel::EMERGENCY;
    public const TYPE_LOGIN = 1;
    public const TYPE_SQL = 2;
    public const TYPE_LDAP = 3;
    public const TYPE_UPGRADE = 4;
    public const TYPE_PLUGIN_PACK_MANAGER = 5;
    public const TYPE_BUSINESS_LOG = 6;

    /** @var list<string> the PSR-3 levels accepted by {@see self::log()} */
    private const VALID_LEVELS = [
        self::LEVEL_DEBUG,
        self::LEVEL_NOTICE,
        self::LEVEL_INFO,
        self::LEVEL_WARNING,
        self::LEVEL_ERROR,
        self::LEVEL_CRITICAL,
        self::LEVEL_ALERT,
        self::LEVEL_EMERGENCY,
    ];

    /** @var array<string,LoggerInterface> memoized loggers, keyed by channel value */
    private array $loggers = [];

    /**
     * @param array<int,string> $customLogFiles unused, kept for BC
     * @param string $pathLogFile unused, kept for BC
     */
    public function __construct(array $customLogFiles = [], string $pathLogFile = '')
    {
    }

    /**
     * Factory
     *
     * @param array<int,string> $customLogs
     */
    public static function create(array $customLogs = [], string $pathLogFile = ''): CentreonLog
    {
        return new CentreonLog($customLogs, $pathLogFile);
    }

    /**
     * @param int $logTypeId one of the TYPE_* constants
     * @param string $level one of the LEVEL_* constants (PSR-3)
     * @param array<string,mixed> $customContext
     */
    public function log(
        int $logTypeId,
        string $level,
        string $message,
        array $customContext = [],
        ?Throwable $exception = null,
    ): void {
        if ($message === '') {
            return;
        }

        if ($exception !== null) {
            $customContext['exception'] = $exception;
        }

        $this->getLoggerForType($logTypeId)->log(
            self::normalizeLevel($level),
            $message,
            $customContext,
        );
    }

    /**
     * @param array<string,mixed> $customContext
     */
    public function debug(int $logTypeId, string $message, array $customContext = [], ?Throwable $exception = null): void
    {
        $this->log($logTypeId, self::LEVEL_DEBUG, $message, $customContext, $exception);
    }

    /**
     * @param array<string,mixed> $customContext
     */
    public function notice(int $logTypeId, string $message, array $customContext = [], ?Throwable $exception = null): void
    {
        $this->log($logTypeId, self::LEVEL_NOTICE, $message, $customContext, $exception);
    }

    /**
     * @param array<string,mixed> $customContext
     */
    public function info(int $logTypeId, string $message, array $customContext = [], ?Throwable $exception = null): void
    {
        $this->log($logTypeId, self::LEVEL_INFO, $message, $customContext, $exception);
    }

    /**
     * @param array<string,mixed> $customContext
     */
    public function warning(int $logTypeId, string $message, array $customContext = [], ?Throwable $exception = null): void
    {
        $this->log($logTypeId, self::LEVEL_WARNING, $message, $customContext, $exception);
    }

    /**
     * @param array<string,mixed> $customContext
     */
    public function error(int $logTypeId, string $message, array $customContext = [], ?Throwable $exception = null): void
    {
        $this->log($logTypeId, self::LEVEL_ERROR, $message, $customContext, $exception);
    }

    /**
     * @param array<string,mixed> $customContext
     */
    public function critical(int $logTypeId, string $message, array $customContext = [], ?Throwable $exception = null): void
    {
        $this->log($logTypeId, self::LEVEL_CRITICAL, $message, $customContext, $exception);
    }

    /**
     * @param array<string,mixed> $customContext
     */
    public function alert(int $logTypeId, string $message, array $customContext = [], ?Throwable $exception = null): void
    {
        $this->log($logTypeId, self::LEVEL_ALERT, $message, $customContext, $exception);
    }

    /**
     * @param array<string,mixed> $customContext
     */
    public function emergency(int $logTypeId, string $message, array $customContext = [], ?Throwable $exception = null): void
    {
        $this->log($logTypeId, self::LEVEL_EMERGENCY, $message, $customContext, $exception);
    }

    /**
     * @param int $logTypeId
     * @param string $logFileName
     *
     * @deprecated no-op kept for BC
     */
    public function pushLogFileHandler(int $logTypeId, string $logFileName): CentreonLog
    {
        return $this;
    }

    /**
     * @deprecated paths are derived from APP_ENV
     */
    public function setPathLogFile(string $pathLogFile): CentreonLog
    {
        return $this;
    }

    /**
     * @param int $id
     * @param string $str
     * @param int $print
     * @param int $page
     * @param int $option
     *
     * @deprecated use {@see CentreonLog::log()} instead
     */
    public function insertLog($id, $str, $print = 0, $page = 0, $option = 0): void
    {
        $message = "{$page}|{$option}|{$str}";

        if ($print) {
            echo $str;
        }

        $this->log(logTypeId: $id, level: self::LEVEL_ERROR, message: $message);
    }

    /**
     * Maps the given level to a known PSR-3 level. Falls back to {@see self::LEVEL_ERROR}
     * for empty or unknown values so a bad caller never silently drops the record
     * (Monolog would otherwise reject an unknown level).
     */
    private static function normalizeLevel(string $level): string
    {
        $normalized = mb_strtolower($level);

        return in_array($normalized, self::VALID_LEVELS, true) ? $normalized : self::LEVEL_ERROR;
    }

    private function getLoggerForType(int $logTypeId): LoggerInterface
    {
        $channel = match ($logTypeId) {
            self::TYPE_LOGIN, self::TYPE_LDAP => LogChannelEnum::AUTHENTICATION,
            self::TYPE_UPGRADE => LogChannelEnum::UPGRADE,
            self::TYPE_PLUGIN_PACK_MANAGER => LogChannelEnum::PLUGIN_PACK_MANAGER,
            default => LogChannelEnum::WEB,
        };

        // Memoize per channel: Logger::create() rebuilds a MonologAdapter with fresh
        // handlers/processors on every call, which is wasteful on hot logging paths.
        return $this->loggers[$channel->value] ??= Logger::create($channel);
    }
}
