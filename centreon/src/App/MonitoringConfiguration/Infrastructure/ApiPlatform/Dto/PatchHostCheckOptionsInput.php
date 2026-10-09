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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto;

use App\MonitoringConfiguration\Infrastructure\Validator\CheckCommandType;
use App\MonitoringConfiguration\Infrastructure\Validator\ValidCommandArguments;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The check command of a host and the arguments it is called with. A key left out is left untouched,
 * so unlike {@see CheckOptionsInput} the arguments are allowed without a command: they then apply to
 * the command the host already has.
 */
final readonly class PatchHostCheckOptionsInput
{
    /**
     * @param ?int $commandId the check command to run, null removes it
     * @param list<string> $args ordered check-command arguments, an empty list clears them
     */
    public function __construct(
        #[Assert\Sequentially([
            new Assert\Positive(),
            new CheckCommandType(),
        ])]
        public ?int $commandId = null,

        #[Assert\All([new Assert\Type('string')])]
        #[ValidCommandArguments('check command')]
        public array $args = [],
    ) {
    }
}
