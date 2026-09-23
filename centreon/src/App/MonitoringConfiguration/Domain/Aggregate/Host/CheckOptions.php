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

    /** @var list<HostMacro> */
    public array $macros;

    /**
     * @param ?CommandId $checkCommandId the check command to run against the host, null when none is set
     * @param array<int, string> $args ordered check-command arguments (reindexed to a list, so upstream
     *                                 filtering that leaves key gaps is tolerated); legacy stores them
     *                                 bang-joined in host.command_command_id_arg1, so the join and
     *                                 #BR#/#T#/#R# encoding belong to the persistence layer, not here
     * @param array<int, HostMacro> $macros the host's own custom macros ($_HOST<NAME>$); unrelated to the
     *                                      check command, they live here only because the ticket groups
     *                                      them under check_options
     */
    public function __construct(
        public ?CommandId $checkCommandId,
        array $args = [],
        array $macros = [],
    ) {
        // Arguments require a check command; reject them when none is set (mirrors the API-boundary check).
        if (! $checkCommandId instanceof CommandId) {
            Assert::isEmpty($args, 'Check command arguments require a check command to be set.');
        }

        Assert::allString($args);
        // Storage bang-joins the arguments and encodes \n\t\r as #BR#/#T#/#R# (CommandArgumentsFormatter),
        // matching legacy. The legacy read path splits on '!' and decodes those tokens, so an argument
        // carrying the '!' delimiter or a literal #BR#/#T#/#R# would not round-trip; raw \n\t\r are fine
        // because the formatter encodes them. Same rule as DataProcessing::$eventHandlerArgs.
        foreach (['!', '#BR#', '#T#', '#R#'] as $reserved) {
            Assert::allNotContains($args, $reserved, 'CheckOptions::args must not contain the "!" delimiter or a #BR#/#T#/#R# escape token.');
        }

        $this->args = array_values($args);
        $this->macros = array_values($macros);
    }
}
