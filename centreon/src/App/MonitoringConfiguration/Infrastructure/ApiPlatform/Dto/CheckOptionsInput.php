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

use App\MonitoringConfiguration\Infrastructure\Service\CommandArgumentsFormatter;
use App\MonitoringConfiguration\Infrastructure\Validator\CheckCommandType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class CheckOptionsInput
{
    /**
     * @param ?int $commandId the check command to run, null when none is set
     * @param list<string> $args ordered check-command arguments; only meaningful with a command
     * @param list<HostMacroInput> $macros the host's own custom macros
     */
    public function __construct(
        #[Assert\Sequentially([
            new Assert\Positive(),
            new CheckCommandType(),
        ])]
        public ?int $commandId = null,

        #[Assert\All([
            new Assert\Type('string'),
            // Storage bang-joins the arguments and encodes \n\t\r as #BR#/#T#/#R#
            // (CommandArgumentsFormatter). The legacy reader splits on '!' and decodes those tokens,
            // so an argument carrying the '!' delimiter or a literal #BR#/#T#/#R# would not round-trip;
            // raw \n\t\r stay allowed because the formatter encodes them. Same rule as
            // DataProcessingInput::$eventHandlerArgs.
            new Assert\Regex(
                pattern: '/!/',
                match: false,
                message: 'A check command argument cannot contain "!".',
            ),
            new Assert\Regex(
                pattern: '/#(?:BR|T|R)#/',
                match: false,
                message: 'A check command argument cannot contain the reserved escape tokens #BR#, #T# or #R#.',
            ),
        ])]
        public array $args = [],

        #[Assert\Valid]
        public array $macros = [],
    ) {
    }

    #[Assert\Callback]
    public function validateArgumentsRequireACommand(ExecutionContextInterface $context): void
    {
        // Mirrors the CheckOptions domain invariant, surfaced as a 422 field violation instead of
        // the 500 the value object's assertion would otherwise produce for an API client.
        if ($this->commandId === null && $this->args !== []) {
            $context->buildViolation('Check command arguments require a check command to be set.')
                ->atPath('args')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    public function validateFormattedArgumentsFitStorage(ExecutionContextInterface $context): void
    {
        // No per-argument or count limit: only the single string the repository ultimately stores
        // in the TEXT column `host.command_command_id_arg1` is bounded. Reuse the exact formatter
        // the repository uses so the measured length is precisely what will be persisted.
        $args = array_values(array_filter($this->args, 'is_string'));
        if (count($args) !== count($this->args)) {
            // Non-string entries are already reported by the Assert\All(Type) constraint above.
            return;
        }

        $formatted = CommandArgumentsFormatter::format($args);
        // Byte length ('8bit'), not character count: the TEXT column limit is in bytes, so a
        // multi-byte argument must be measured as the bytes it will actually occupy.
        if ($formatted !== null && \mb_strlen($formatted, '8bit') > CommandArgumentsFormatter::MAX_STORAGE_LENGTH) {
            $context->buildViolation('The check command arguments are too long.')
                ->atPath('args')
                ->addViolation();
        }
    }
}
