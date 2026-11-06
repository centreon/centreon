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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service;

use App\MonitoringConfiguration\Infrastructure\Validator\CheckCommandType;
use App\MonitoringConfiguration\Infrastructure\Validator\ValidCommandArguments;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Whether a command is set, directly or through the template chain, is checked with the whole payload
 * ({@see \App\MonitoringConfiguration\Infrastructure\Validator\ServiceCommandInheritance}).
 */
final readonly class ServiceCheckOptionsInput
{
    /**
     * @param ?int $commandId the check command, null to use the one of the template chain
     * @param list<string> $args ordered check-command arguments
     * @param list<ServiceMacroInput> $macros the service's own custom macros, and the inherited ones it overrides
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

        #[Assert\Valid]
        public array $macros = [],
    ) {
    }

    /**
     * Either every argument is filled or none is: a list of empty arguments only is no argument.
     */
    #[Assert\Callback]
    public function validateArgumentsAreAllOrNone(ExecutionContextInterface $context): void
    {
        if (
            array_any($this->args, static fn (mixed $argument): bool => $argument === '')
            && array_any($this->args, static fn (mixed $argument): bool => $argument !== '')
        ) {
            $context->buildViolation('Either fill every argument or leave them all empty.')
                ->atPath('args')
                ->addViolation();
        }
    }
}
