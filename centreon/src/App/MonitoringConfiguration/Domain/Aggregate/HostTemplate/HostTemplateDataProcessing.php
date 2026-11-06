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

namespace App\MonitoringConfiguration\Domain\Aggregate\HostTemplate;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\CommandArguments;
use App\Shared\Domain\Aggregate\TriStateEnum;

final readonly class HostTemplateDataProcessing
{
    /** @var list<string> */
    public array $eventHandlerArgs;

    /**
     * @param array<int, string> $eventHandlerArgs
     */
    public function __construct(
        public TriStateEnum $checkFreshness = TriStateEnum::UseDefault,
        public TriStateEnum $flapDetectionEnabled = TriStateEnum::UseDefault,
        public TriStateEnum $eventHandlerEnabled = TriStateEnum::UseDefault,
        public ?int $acknowledgmentTimeout = null,
        public ?int $freshnessThreshold = null,
        public ?int $lowFlapThreshold = null,
        public ?int $highFlapThreshold = null,
        public ?CommandId $eventHandlerCommandId = null,
        array $eventHandlerArgs = [],
    ) {
        CommandArguments::assertValid($eventHandlerArgs, 'HostTemplateDataProcessing::eventHandlerArgs');

        $this->eventHandlerArgs = array_values($eventHandlerArgs);
    }
}
