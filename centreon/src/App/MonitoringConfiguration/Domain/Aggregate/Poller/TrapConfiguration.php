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

final readonly class TrapConfiguration
{
    public const DEFAULT_INIT_SCRIPT_PATH = 'centreontrapd';
    public const DEFAULT_SNMP_TRAP_PATH_CONF = '/etc/snmp/centreon_traps/';

    public function __construct(
        public ?string $initScriptPath = self::DEFAULT_INIT_SCRIPT_PATH,
        public ?string $snmpTrapPathConf = self::DEFAULT_SNMP_TRAP_PATH_CONF,
    ) {
        Assert::nullOrMaxLength($initScriptPath, 255);
        Assert::nullOrMaxLength($snmpTrapPathConf, 255);
    }
}
