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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate;

use ApiPlatform\Metadata\ApiProperty;
use App\MonitoringConfiguration\Infrastructure\Validator\CheckCommandType;
use App\MonitoringConfiguration\Infrastructure\Validator\ValidCommandArguments;
use App\Shared\Infrastructure\Validator\Constraints\ExclusiveKeys;
use App\Shared\Infrastructure\Validator\Constraints\IdList;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ExclusiveKeys([
    ['macros', 'macrosToUpsert', 'macrosToRemove'],
])]
final readonly class PatchHostTemplateCheckOptionsInput
{
    /**
     * @param ?int $commandId null removes the command and keeps the arguments
     * @param list<string> $args an empty list clears them; accepted without a command
     * @param list<HostTemplateMacroInput> $macros the template's own macros: the ones left out are removed
     * @param list<HostTemplateMacroInput> $macrosToUpsert written, the other ones kept
     * @param list<int> $macrosToRemove ids of the template's own macros
     */
    public function __construct(
        #[Assert\Sequentially([new Assert\Positive(), new CheckCommandType()])]
        public ?int $commandId = null,

        #[Assert\All([new Assert\Type('string')])]
        #[ValidCommandArguments('check command')]
        public array $args = [],

        #[ApiProperty(description: 'Replaces the macros: the template\'s own macros left out are removed. Each macro points to the macro it changes by id (and parent, for an inherited one), or is new without an id. A password macro without value keeps its stored value. Only one of this, "macros_to_upsert" and "macros_to_remove" can be sent.')]
        #[Assert\Valid]
        public array $macros = [],

        #[ApiProperty(description: 'Writes these macros and keeps the other ones. Same macros as above.')]
        #[Assert\Valid]
        public array $macrosToUpsert = [],

        #[ApiProperty(description: "The ids of the template's own macros to remove.")]
        #[IdList]
        public array $macrosToRemove = [],
    ) {
    }

    #[Assert\Callback]
    public function validateMacroNamesAreUnique(ExecutionContextInterface $context): void
    {
        foreach (['macros' => $this->macros, 'macrosToUpsert' => $this->macrosToUpsert] as $key => $macros) {
            $names = array_map(static fn (HostTemplateMacroInput $macro): string => mb_strtoupper(trim($macro->name)), $macros);
            if (count($names) !== count(array_unique($names))) {
                $context->buildViolation('A host template cannot have two macros with the same name.')
                    ->atPath($key)
                    ->addViolation();
            }
        }
    }
}
