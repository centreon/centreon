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

final readonly class EngineLoggerConfiguration
{
    public function __construct(
        public LoggerTypeEnum $loggerType = LoggerTypeEnum::File,
        public LogLevelEnum $configLevel = LogLevelEnum::Info,
        public LogLevelEnum $eventsLevel = LogLevelEnum::Info,
        public LogLevelEnum $checksLevel = LogLevelEnum::Info,
        public LogLevelEnum $notificationsLevel = LogLevelEnum::Err,
        public LogLevelEnum $eventbrokerLevel = LogLevelEnum::Err,
        public LogLevelEnum $externalCommandLevel = LogLevelEnum::Info,
        public LogLevelEnum $commandsLevel = LogLevelEnum::Err,
        public LogLevelEnum $downtimesLevel = LogLevelEnum::Err,
        public LogLevelEnum $commentsLevel = LogLevelEnum::Err,
        public LogLevelEnum $macrosLevel = LogLevelEnum::Err,
        public LogLevelEnum $processLevel = LogLevelEnum::Info,
        public LogLevelEnum $runtimeLevel = LogLevelEnum::Err,
        public LogLevelEnum $functionsLevel = LogLevelEnum::Err,
        public LogLevelEnum $otlLevel = LogLevelEnum::Err,
    ) {
    }
}
