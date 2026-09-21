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

use App\MonitoringConfiguration\Infrastructure\Validator\ValidEventHandlerCommand;
use App\Shared\Domain\TriStateEnum;
use App\Shared\Infrastructure\Validator\Constraints\WhenPlatform;
use Symfony\Component\Validator\Constraints as Assert;

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
        #[Assert\All([new Assert\Type('string')])]
        public array $eventHandlerArgs = [],
    ) {
    }
}
