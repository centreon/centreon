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

namespace App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\CommandArguments;
use Webmozart\Assert\Assert;

/**
 * The service's own check command, its arguments and its own custom macros.
 *
 * Unlike the host's, arguments do not require a check command here: a service may pass its own arguments to
 * the check command of its template chain, which only the template chain can tell; that rule is the write
 * path's. Two own macros of the same name are kept as they are: duplicates are refused on write, never
 * silently dropped.
 */
final readonly class ServiceCheckOptions
{
    /** @var list<string> */
    public array $args;

    /** @var list<ServiceMacro> */
    public array $macros;

    /**
     * @param ?CommandId $checkCommandId the service's own check command, null when none is set
     * @param array<int, string> $args ordered check-command arguments, reindexed to a list; the bang-joined
     *                                 storage form belongs to the persistence layer
     * @param array<int, ServiceMacro> $macros the service's own custom macros ($_SERVICE<NAME>$); inherited
     *                                         macros belong to the templates and the command, never to it
     */
    public function __construct(
        public ?CommandId $checkCommandId,
        array $args = [],
        array $macros = [],
    ) {
        CommandArguments::assertValid($args, 'ServiceCheckOptions::args');

        Assert::allFalse(
            array_map(static fn (ServiceMacro $macro): bool => $macro->isInherited(), $macros),
            'A service only owns its own macros.',
        );

        $this->args = array_values($args);
        $this->macros = array_values($macros);
    }
}
