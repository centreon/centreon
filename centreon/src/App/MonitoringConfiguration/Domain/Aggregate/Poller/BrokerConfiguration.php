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

final readonly class BrokerConfiguration
{
    public const DEFAULT_RELOAD_COMMAND = 'systemctl reload cbd';
    public const DEFAULT_CONFIGURATION_PATH = '/etc/centreon-broker';
    public const DEFAULT_MODULES_PATH = '/usr/share/centreon/lib/centreon-broker';
    public const DEFAULT_LOGS_PATH = '/var/log/centreon-broker';

    public function __construct(
        public ?string $reloadCommand = self::DEFAULT_RELOAD_COMMAND,
        public ?string $configurationPath = self::DEFAULT_CONFIGURATION_PATH,
        public ?string $modulesPath = self::DEFAULT_MODULES_PATH,
        public ?string $logsPath = self::DEFAULT_LOGS_PATH,
    ) {
        Assert::nullOrMaxLength($reloadCommand, 255);
        Assert::nullOrMaxLength($configurationPath, 255);
        Assert::nullOrMaxLength($modulesPath, 255);
        Assert::nullOrMaxLength($logsPath, 255);
    }
}
