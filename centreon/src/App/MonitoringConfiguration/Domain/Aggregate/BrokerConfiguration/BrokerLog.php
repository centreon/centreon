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

namespace App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration;

/**
 * One `cfg_centreonbroker_log` entry: a logger category at a verbosity level. The Domain speaks
 * in names; the Infrastructure layer resolves them to `cb_log` / `cb_log_level` ids at persistence.
 */
final readonly class BrokerLog
{
    public function __construct(
        public BrokerLoggerEnum $logger,
        public BrokerLogLevelEnum $level,
    ) {
    }
}
