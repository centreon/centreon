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

namespace App\MonitoringConfiguration\Domain\Aggregate\Poller;

use Webmozart\Assert\Assert;

final readonly class EngineConfiguration
{
    public const DEFAULT_START_COMMAND = 'systemctl start centengine';
    public const DEFAULT_STOP_COMMAND = 'systemctl stop centengine';
    public const DEFAULT_RESTART_COMMAND = 'systemctl restart centengine';
    public const DEFAULT_RELOAD_COMMAND = 'systemctl reload centengine';
    public const DEFAULT_BINARY_PATH = '/usr/sbin/centengine';
    public const DEFAULT_STATISTICS_BINARY_PATH = '/usr/sbin/centenginestats';
    public const DEFAULT_PERFDATA_FILE_PATH = '/var/log/centreon-engine/service-perfdata';

    public function __construct(
        public ?string $startCommand = self::DEFAULT_START_COMMAND,
        public ?string $stopCommand = self::DEFAULT_STOP_COMMAND,
        public ?string $restartCommand = self::DEFAULT_RESTART_COMMAND,
        public ?string $reloadCommand = self::DEFAULT_RELOAD_COMMAND,
        public ?string $binaryPath = self::DEFAULT_BINARY_PATH,
        public ?string $statisticsBinaryPath = self::DEFAULT_STATISTICS_BINARY_PATH,
        public ?string $perfdataFilePath = self::DEFAULT_PERFDATA_FILE_PATH,
    ) {
        Assert::nullOrMaxLength($startCommand, 255);
        Assert::nullOrMaxLength($stopCommand, 255);
        Assert::nullOrMaxLength($restartCommand, 255);
        Assert::nullOrMaxLength($reloadCommand, 255);
        Assert::nullOrMaxLength($binaryPath, 255);
        Assert::nullOrMaxLength($statisticsBinaryPath, 255);
        Assert::nullOrMaxLength($perfdataFilePath, 255);
    }
}
