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
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class CheckOptionsInput
{
    /**
     * @param ?int $commandId the check command to run, null when none is set
     * @param list<string> $args ordered check-command arguments; only meaningful with a command
     */
    public function __construct(
        #[Assert\Sequentially([
            new Assert\Positive(),
            new CheckCommandType(),
        ])]
        public ?int $commandId = null,

        #[Assert\All([new Assert\Type('string')])]
        public array $args = [],
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
}
