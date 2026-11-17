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

use ApiPlatform\Metadata\ApiProperty;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\ServiceStalkingOptionEnumResolver;
use App\MonitoringConfiguration\Infrastructure\Validator\ValidCommandArguments;
use App\MonitoringConfiguration\Infrastructure\Validator\ValidEventHandlerCommand;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Infrastructure\Validator\Constraints\WhenPlatform;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The "Advanced" section of the form: freshness, flap detection, event handler, obsess, retention and stalking
 * directives, and the comment. Whether an event handler is set, directly or through the template chain, is checked
 * with the whole payload.
 */
final readonly class ServiceAdvancedInput
{
    /**
     * @param list<string> $stalkingOptions
     * @param list<string> $eventHandlerArgs
     */
    public function __construct(
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\IsNull(message: 'obsess_over_service is not available on a Cloud platform.'),
        ])]
        public ?TriStateEnum $obsessOverService = null,

        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\IsNull(message: 'acknowledgment_timeout is not available on a Cloud platform.'),
        ])]
        #[Assert\Positive]
        public ?int $acknowledgmentTimeout = null,

        public ?TriStateEnum $checkFreshness = null,

        #[Assert\PositiveOrZero]
        public ?int $freshnessThreshold = null,

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
            new Assert\IsNull(message: 'retain_status_information is not available on a Cloud platform.'),
        ])]
        public ?TriStateEnum $retainStatusInformation = null,

        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\IsNull(message: 'retain_non_status_information is not available on a Cloud platform.'),
        ])]
        public ?TriStateEnum $retainNonStatusInformation = null,

        #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ServiceStalkingOptionEnumResolver::API_VALUES]])]
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\Count(exactly: 0, exactMessage: 'stalking_options is not available on a Cloud platform.'),
        ])]
        #[Assert\All([new Assert\Choice(callback: [self::class, 'apiStalkingOptions'])])]
        public array $stalkingOptions = [],

        public ?TriStateEnum $eventHandlerEnabled = null,

        #[Assert\Sequentially([
            new Assert\Positive(),
            new ValidEventHandlerCommand(),
        ])]
        public ?int $eventHandlerCommandId = null,

        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\Count(exactly: 0, exactMessage: 'event_handler_args is not available on a Cloud platform.'),
        ])]
        #[Assert\All([new Assert\Type('string')])]
        #[ValidCommandArguments('event handler')]
        public array $eventHandlerArgs = [],

        #[Assert\Sequentially([
            new Assert\NotBlank(allowNull: true, normalizer: 'trim'),
            // Bounded in bytes, as the TEXT column and the value object are.
            new Assert\Length(
                max: ServiceExtendedInformations::MAX_TEXT_BYTES,
                countUnit: Assert\Length::COUNT_BYTES,
                maxMessage: 'This value is too long. It should have {{ limit }} bytes or less.',
            ),
        ])]
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\Blank(message: 'This field is not available on a Cloud platform.'),
        ])]
        public ?string $comment = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function apiStalkingOptions(): array
    {
        return array_map(ServiceStalkingOptionEnumResolver::toString(...), ServiceStalkingOptionEnum::cases());
    }
}
