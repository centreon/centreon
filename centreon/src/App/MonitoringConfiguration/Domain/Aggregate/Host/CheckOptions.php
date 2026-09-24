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

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use Webmozart\Assert\Assert;

final readonly class CheckOptions
{
    /** @var list<string> */
    public array $args;

    /**
     * @param ?CommandId $checkCommandId the check command to run against the host, null when none is set
     * @param array<int, string> $args ordered check-command arguments (reindexed to a list, so upstream
     *                                 filtering that leaves key gaps is tolerated); legacy stores them
     *                                 bang-joined in host.command_command_id_arg1, so the join and
     *                                 #BR#/#T#/#R# encoding belong to the persistence layer, not here
     */
    public function __construct(
        public ?CommandId $checkCommandId,
        array $args = [],
    ) {
        // Arguments only make sense alongside a check command: without a
        // command there is nothing for them to be passed to, so legacy hides the field entirely.
        if (! $checkCommandId instanceof CommandId) {
            Assert::isEmpty($args, 'Check command arguments require a check command to be set.');
        }

        $this->args = array_values($args);
    }
}
