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
use App\MonitoringConfiguration\Infrastructure\Validator\ValidEventHandlerCommand;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Infrastructure\Validator\Constraints\WhenPlatform;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The "Data Processing" sub-object of the CreateHost payload. Three-state directives are typed as
 * {@see TriStateEnum} (a null / absent value means "use default"). On-premise-only fields carry a
 * WhenPlatform(forCloud) guard that rejects them on a Cloud platform.
 */
final readonly class DataProcessingInput
{
    /**
     * @param list<string> $eventHandlerArgs
     */
    public function __construct(
        public ?TriStateEnum $checkFreshness = null,

        #[Assert\PositiveOrZero]
        public ?int $freshnessThreshold = null,

        public ?TriStateEnum $eventHandlerEnabled = null,

        #[Assert\Sequentially([
            new Assert\Positive(),
            new ValidEventHandlerCommand(),
        ])]
        public ?int $eventHandlerCommandId = null,

        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\IsNull(message: 'acknowledgment_timeout is not available on a Cloud platform.'),
        ])]
        #[Assert\Positive]
        public ?int $acknowledgmentTimeout = null,

        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\IsNull(message: 'flap_detection_enabled is not available on a Cloud platform.'),
        ])]
        public ?TriStateEnum $flapDetectionEnabled = null,

        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\IsNull(message: 'low_flap_threshold is not available on a Cloud platform.'),
        ])]
        #[Assert\Range(min: 0, max: 100)]
        public ?int $lowFlapThreshold = null,

        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\IsNull(message: 'high_flap_threshold is not available on a Cloud platform.'),
        ])]
        #[Assert\Range(min: 0, max: 100)]
        public ?int $highFlapThreshold = null,

        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\Count(max: 0, maxMessage: 'event_handler_args is not available on a Cloud platform.'),
        ])]
        #[Assert\All([
            new Assert\Type('string'),
            // Storage bang-joins the arguments and encodes \n\t\r as #BR#/#T#/#R#
            // (CommandArgumentsFormatter), matching legacy. The legacy reader splits on '!' and decodes
            // those tokens, so an argument carrying the '!' delimiter or a literal #BR#/#T#/#R# would not
            // round-trip; raw \n\t\r stay allowed because the formatter encodes them. Same rule as
            // CheckOptionsInput::$args.
            new Assert\Regex(
                pattern: '/!/',
                match: false,
                message: 'An event handler argument cannot contain "!".',
            ),
            new Assert\Regex(
                pattern: '/#(?:BR|T|R)#/',
                match: false,
                message: 'An event handler argument cannot contain the reserved escape tokens #BR#, #T# or #R#.',
            ),
        ])]
        public array $eventHandlerArgs = [],
    ) {
    }

    #[Assert\Callback]
    public function validateFormattedArgumentsFitStorage(ExecutionContextInterface $context): void
    {
        // No per-argument or count limit: only the single string the repository ultimately stores
        // in the TEXT column `host.command_command_id_arg2` is bounded. Reuse the exact formatter
        // the repository uses so the measured length is precisely what will be persisted.
        $args = array_values(array_filter($this->eventHandlerArgs, 'is_string'));
        if (count($args) !== count($this->eventHandlerArgs)) {
            // Non-string entries are already reported by the Assert\All(Type) constraint above.
            return;
        }

        $formatted = CommandArgumentsFormatter::format($args);
        // Byte length ('8bit'), not character count: the TEXT column limit is in bytes, so a
        // multi-byte argument must be measured as the bytes it will actually occupy.
        if ($formatted !== null && \mb_strlen($formatted, '8bit') > CommandArgumentsFormatter::MAX_STORAGE_LENGTH) {
            $context->buildViolation('The event handler arguments are too long.')
                ->atPath('eventHandlerArgs')
                ->addViolation();
        }
    }
}
