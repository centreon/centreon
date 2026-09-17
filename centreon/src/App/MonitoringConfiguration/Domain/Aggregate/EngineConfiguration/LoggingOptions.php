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

final readonly class LoggingOptions
{
    public const DEFAULT_DEBUG_FILE = '/var/log/centreon-engine/centengine.debug';
    public const DEFAULT_DEBUG_LEVEL = 0;
    public const DEFAULT_DEBUG_LEVEL_OPT = '0';
    public const DEFAULT_DEBUG_VERBOSITY = 1;
    public const DEFAULT_MAX_DEBUG_FILE_SIZE = 1000000000;

    public function __construct(
        public bool $useSyslog = false,
        public bool $logNotifications = true,
        public bool $logServiceRetries = true,
        public bool $logHostRetries = true,
        public bool $logEventHandlers = true,
        public bool $logExternalCommands = true,
        public bool $logPassiveChecks = true,
        public bool $logPid = true,
        public string $debugFile = self::DEFAULT_DEBUG_FILE,
        public int $debugLevel = self::DEFAULT_DEBUG_LEVEL,
        public string $debugLevelOpt = self::DEFAULT_DEBUG_LEVEL_OPT,
        public int $debugVerbosity = self::DEFAULT_DEBUG_VERBOSITY,
        public int $maxDebugFileSize = self::DEFAULT_MAX_DEBUG_FILE_SIZE,
        public EngineLoggerConfiguration $loggerConfiguration = new EngineLoggerConfiguration(),
    ) {
    }
}
