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

namespace App\MonitoringConfiguration\Application\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;

/**
 * A host whose secrets have been moved to the vault.
 *
 * @see HostSecretsSynchronizer
 */
final readonly class SynchronizedHost
{
    /**
     * @param bool $secretRewritten whether a secret was written to the vault: the host may then keep the very
     *                              same references, since a reference only depends on its entry and its key
     */
    public function __construct(
        public Host $host,
        public bool $secretRewritten,
    ) {
    }
}
