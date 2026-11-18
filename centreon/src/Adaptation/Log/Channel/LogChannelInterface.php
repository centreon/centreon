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

namespace Adaptation\Log\Channel;

/**
 * A logging channel routed through the unified Monolog pipeline.
 *
 * Implemented both by the core {@see \Adaptation\Log\Enum\LogChannelEnum} (whose
 * files follow the platform `{APP_ENV}.{slug}.log` convention) and by
 * {@see ModuleLogChannel} (which preserves a module's historical log file name).
 * Letting the channel own its file name keeps {@see \Adaptation\Log\Adapter\MonologAdapter}
 * free of any per-channel naming rule.
 */
interface LogChannelInterface
{
    /**
     * The Monolog channel name carried on every record of this channel.
     */
    public function getChannelName(): string;

    /**
     * The log file name (without directory) records of this channel are written to.
     *
     * @param string $appEnv the current APP_ENV (`prod`, `dev`, `test`); core channels
     *                       prefix the file with it, module channels keep their literal
     *                       historical name and ignore it
     */
    public function getLogFileName(string $appEnv): string;
}
