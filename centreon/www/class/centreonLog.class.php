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
 * Class
 *
 * @class CentreonUserLog
 *
 * @deprecated since MON-151077 — log directly through
 *             {@see \Adaptation\Log\Logger::create()} with the appropriate
 *             {@see LogChannelEnum}
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
     * CentreonUserLog constructor
     *
     * @param int $uid
     * @param CentreonDB $pearDB kept for backward compatibility; the connection
     *                           is no longer consulted because the log path is
     *                           driven by APP_ENV / LogChannelEnum now
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

        // Replace special chars that used to leak through the pipe-separated
        // legacy format. Kept for parity with the original behaviour.
        $message = str_replace(['`', '*'], ['', '\*'], (string) $str);

        $context = [
            'uid' => $this->uid,
            'page' => $page,
            'option' => $option,
        ];

        Logger::create(self::resolveChannel((int) $id))->info($message, $context);
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
            self::$instance = new CentreonUserLog($uid, CentreonDB::factory('centreon'));
        }

        return self::$instance;
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
 * Class
 *
 * @class CentreonLog
 *
 * @deprecated since MON-151077 — log directly through
 *             {@see \Adaptation\Log\Logger::create()} with the appropriate
 *             {@see LogChannelEnum}
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
     * Log type ids — kept for backward compatibility with callers that still
     * pass `CentreonLog::TYPE_*`. Routing is now driven by LogChannelEnum.
     */
    public const TYPE_LOGIN = 1;
    public const TYPE_SQL = 2;
    public const TYPE_LDAP = 3;
    public const TYPE_UPGRADE = 4;
    public const TYPE_PLUGIN_PACK_MANAGER = 5;
    public const TYPE_BUSINESS_LOG = 6;

    /**
     * @param array<int,string> $customLogFiles ignored — kept for BC; channels
     *                                          are now resolved via LogChannelEnum
     * @param string $pathLogFile ignored — paths are derived from APP_ENV
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
            // Pass the Throwable as-is; ExceptionFormatterProcessor wired in
            // MonologAdapter unwraps the chain and rewrites context.exception.
            $customContext['exception'] = $exception;
        }

        $this->getLoggerForType($logTypeId)->log(
            $level !== '' ? mb_strtolower($level) : self::LEVEL_ERROR,
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
     * @deprecated since MON-151077 — file routing is now driven by LogChannelEnum
     *             and the monolog config; this method is a no-op kept for
     *             backward compatibility with legacy callers and modules
     */
    public function pushLogFileHandler(int $logTypeId, string $logFileName): CentreonLog
    {
        return $this;
    }

    /**
     * @deprecated since MON-151077 — paths are derived from APP_ENV
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
     * @deprecated since MON-151077 — use {@see CentreonLog::log()} instead
     */
    public function insertLog($id, $str, $print = 0, $page = 0, $option = 0): void
    {
        $message = "{$page}|{$option}|{$str}";

        if ($print) {
            echo $str;
        }

        $this->log(logTypeId: $id, level: self::LEVEL_ERROR, message: $message);
    }

    private function getLoggerForType(int $logTypeId): LoggerInterface
    {
        $channel = match ($logTypeId) {
            self::TYPE_LOGIN, self::TYPE_LDAP => LogChannelEnum::AUTHENTICATION,
            self::TYPE_UPGRADE => LogChannelEnum::UPGRADE,
            self::TYPE_PLUGIN_PACK_MANAGER => LogChannelEnum::PLUGIN_PACK_MANAGER,
            default => LogChannelEnum::WEB,
        };

        return Logger::create($channel);
    }
}
