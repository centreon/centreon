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

namespace App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\Shared\Application\Command\NonTransactionalCommand;

/**
 * Best-effort, non-atomic by design: re-linking shared services commits on the configuration
 * connection while cloning exclusive ones runs on the legacy connection, so wrapping this in a
 * transaction would roll the re-links back whenever the (session-bound) clone cannot run — e.g. a
 * token-authenticated request. It must therefore stay outside the command bus transaction.
 */
final readonly class DuplicateHostServicesCommand implements NonTransactionalCommand
{
    public function __construct(
        public HostId $sourceHostId,
        public HostId $newHostId,
        public int $duplicatedBy,
    ) {
    }
}
