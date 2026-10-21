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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\Dto;

use ApiPlatform\Metadata\ApiProperty;
use App\MonitoringConfiguration\Infrastructure\Validator\CheckCommandType;
use App\MonitoringConfiguration\Infrastructure\Validator\ValidCommandArguments;
use App\Shared\Infrastructure\Validator\Constraints\ExclusiveKeys;
use App\Shared\Infrastructure\Validator\Constraints\IdList;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The check command of a host and the arguments it is called with. A key left out is left untouched,
 * so unlike {@see CheckOptionsInput} the arguments are allowed without a command: they then apply to
 * the command the host already has.
 *
 * The custom macros have three keys, of which only one can be sent: they are replaced, written, or
 * removed.
 */
#[ExclusiveKeys([
    ['macros', 'macrosToUpsert', 'macrosToRemove'],
])]
final readonly class PatchHostCheckOptionsInput
{
    /**
     * @param ?int $commandId the check command to run, null removes it
     * @param list<string> $args ordered check-command arguments, an empty list clears them
     * @param list<HostMacroInput> $macros the host's own macros: the ones left out are removed
     * @param list<HostMacroInput> $macrosToUpsert the macros to write, the other ones are kept
     * @param list<int> $macrosToRemove the ids of the host's own macros to remove
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

        #[ApiProperty(description: 'Replaces the custom macros: the host\'s own macros left out are removed. Each macro points to the macro it changes by id (and parent, for an inherited one), or is new without an id. A password macro without value keeps its stored value. Only one of this, "macros_to_upsert" and "macros_to_remove" can be sent.')]
        #[Assert\Valid]
        public array $macros = [],

        #[ApiProperty(description: 'Writes these macros and keeps the other ones. Same macros as above.')]
        #[Assert\Valid]
        public array $macrosToUpsert = [],

        #[ApiProperty(description: "The ids of the host's own macros to remove.")]
        #[IdList]
        public array $macrosToRemove = [],
    ) {
    }

    #[Assert\Callback]
    public function validateMacroNamesAreUnique(ExecutionContextInterface $context): void
    {
        foreach (['macros' => $this->macros, 'macrosToUpsert' => $this->macrosToUpsert] as $key => $macros) {
            $seen = [];
            foreach ($macros as $index => $macro) {
                if (! $macro instanceof HostMacroInput) {
                    continue;
                }

                $name = mb_strtoupper(trim($macro->name));
                if (isset($seen[$name])) {
                    $context->buildViolation('Two macros cannot have the same name.')
                        ->atPath(sprintf('%s[%d].name', $key, $index))
                        ->addViolation();
                }

                $seen[$name] = true;
            }
        }
    }
}
