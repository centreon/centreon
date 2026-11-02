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

namespace App\MonitoringConfiguration\Domain\Aggregate\Host;

use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;

/**
 * What a write on a resource of a host needs to know of that host, without loading the whole host: whether it
 * is enabled, and its poller, null when it has none. The poller is the one locked before the write and flagged
 * for a configuration reload after it, while the host is enabled.
 */
final readonly class HostState
{
    public function __construct(
        public bool $activated,
        public ?PollerId $pollerId,
    ) {
    }
}
