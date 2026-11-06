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

use App\MonitoringConfiguration\Infrastructure\Validator\CheckCommandType;
use App\MonitoringConfiguration\Infrastructure\Validator\ValidCommandArguments;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class CreateHostTemplateCheckOptionsInput
{
    /**
     * @param list<string> $args require a check command
     * @param list<HostTemplateMacroInput> $macros
     */
    public function __construct(
        #[Assert\Sequentially([new Assert\Positive(), new CheckCommandType()])]
        public ?int $commandId = null,

        #[Assert\All([new Assert\Type('string')])]
        #[ValidCommandArguments('check command')]
        public array $args = [],

        #[Assert\Valid]
        public array $macros = [],
    ) {
    }

    #[Assert\Callback]
    public function validateArgumentsRequireACommand(ExecutionContextInterface $context): void
    {
        if ($this->commandId === null && $this->args !== []) {
            $context->buildViolation('Check command arguments require a check command to be set.')
                ->atPath('args')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    public function validateMacroNamesAreUnique(ExecutionContextInterface $context): void
    {
        $names = array_map(static fn (HostTemplateMacroInput $macro): string => mb_strtoupper(trim($macro->name)), $this->macros);
        if (count($names) !== count(array_unique($names))) {
            $context->buildViolation('A host template cannot have two macros with the same name.')
                ->atPath('macros')
                ->addViolation();
        }
    }
}
