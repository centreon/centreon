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

declare(strict_types=1);

namespace App\MonitoringConfiguration\Domain\Aggregate\EngineConfiguration;

final readonly class MiscOptions
{
    public const DEFAULT_CONFIG_DIRECTORY = '/etc/centreon-engine/';
    public const DEFAULT_LOG_FILE = '/var/log/centreon-engine/centengine.log';
    public const DEFAULT_STATUS_FILE = '/var/log/centreon-engine/status.dat';
    public const DEFAULT_STATUS_UPDATE_INTERVAL = 60;
    public const DEFAULT_COMMAND_CHECK_INTERVAL = '1s';
    public const DEFAULT_COMMAND_FILE = '/var/lib/centreon-engine/rw/centengine.cmd';
    public const DEFAULT_EXTERNAL_COMMAND_BUFFER_SLOTS = 4096;
    public const DEFAULT_COMMENT = 'Centreon Engine config file for a polling instance';
    public const DEFAULT_DATE_FORMAT = 'euro';
    public const DEFAULT_ILLEGAL_OBJECT_NAME_CHARS = '~!$%^&*"|\'<>?,()=';
    public const DEFAULT_ILLEGAL_MACRO_OUTPUT_CHARS = '`~$^&"|\'<>';
    public const DEFAULT_ADMIN_EMAIL = 'admin@localhost';
    public const DEFAULT_ADMIN_PAGER = 'admin@localhost';
    public const DEFAULT_LOGGER_VERSION = 'log_v2_enabled';
    public const DEFAULT_INSTANCE_HEARTBEAT_INTERVAL = 30;

    public function __construct(
        public string $configDirectory = self::DEFAULT_CONFIG_DIRECTORY,
        public string $logFile = self::DEFAULT_LOG_FILE,
        public string $statusFile = self::DEFAULT_STATUS_FILE,
        public int $statusUpdateInterval = self::DEFAULT_STATUS_UPDATE_INTERVAL,
        public bool $checkExternalCommands = true,
        public string $commandCheckInterval = self::DEFAULT_COMMAND_CHECK_INTERVAL,
        public string $commandFile = self::DEFAULT_COMMAND_FILE,
        public int $externalCommandBufferSlots = self::DEFAULT_EXTERNAL_COMMAND_BUFFER_SLOTS,
        public string $comment = self::DEFAULT_COMMENT,
        public string $dateFormat = self::DEFAULT_DATE_FORMAT,
        public string $illegalObjectNameChars = self::DEFAULT_ILLEGAL_OBJECT_NAME_CHARS,
        public string $illegalMacroOutputChars = self::DEFAULT_ILLEGAL_MACRO_OUTPUT_CHARS,
        public bool $useRegexpMatching = false,
        public bool $useTrueRegexpMatching = false,
        public string $adminEmail = self::DEFAULT_ADMIN_EMAIL,
        public string $adminPager = self::DEFAULT_ADMIN_PAGER,
        public string $loggerVersion = self::DEFAULT_LOGGER_VERSION,
        public int $instanceHeartbeatInterval = self::DEFAULT_INSTANCE_HEARTBEAT_INTERVAL,
        public bool $enableMacrosFilter = false,
        public string $macrosFilter = '',
    ) {
    }
}
