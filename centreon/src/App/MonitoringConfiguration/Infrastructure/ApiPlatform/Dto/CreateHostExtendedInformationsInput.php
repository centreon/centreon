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

use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingMedia;
use App\MonitoringConfiguration\Infrastructure\Validator\ValidGeoCoordinates;
use App\Shared\Infrastructure\Validator\Constraints\WhenPlatform;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateHostExtendedInformationsInput
{
    public function __construct(
        #[Assert\Sequentially([
            new Assert\NotBlank(allowNull: true, normalizer: 'trim'),
            new Assert\Length(min: ExtendedInformations::MIN_NOTE_URL_LENGTH, max: ExtendedInformations::MAX_NOTE_URL_LENGTH),
        ])]
        public ?string $noteUrl = null,

        #[Assert\Sequentially([
            new Assert\NotBlank(allowNull: true, normalizer: 'trim'),
            new Assert\Length(min: ExtendedInformations::MIN_NOTE_LENGTH, max: ExtendedInformations::MAX_NOTE_LENGTH),
        ])]
        public ?string $note = null,

        #[Assert\Sequentially([
            new Assert\NotBlank(allowNull: true, normalizer: 'trim'),
            new Assert\Length(min: ExtendedInformations::MIN_ACTION_URL_LENGTH, max: ExtendedInformations::MAX_ACTION_URL_LENGTH),
        ])]
        public ?string $actionUrl = null,

        #[Assert\Sequentially([new Assert\Positive(), new ExistingMedia()])]
        public ?int $iconId = null,

        #[Assert\Sequentially([
            new Assert\NotBlank(allowNull: true, normalizer: 'trim'),
            new Assert\Length(min: ExtendedInformations::MIN_ALT_ICON_LENGTH, max: ExtendedInformations::MAX_ALT_ICON_LENGTH),
        ])]
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\Blank(message: 'This field is not available on a Cloud platform.'),
        ])]
        public ?string $altIcon = null,

        #[Assert\Sequentially([
            new Assert\NotBlank(allowNull: true, normalizer: 'trim'),
            new Assert\Length(min: ExtendedInformations::MIN_COMMENT_LENGTH, max: ExtendedInformations::MAX_COMMENT_LENGTH),
        ])]
        #[WhenPlatform(forCloud: true, constraints: [
            new Assert\Blank(message: 'This field is not available on a Cloud platform.'),
        ])]
        public ?string $comment = null,

        #[ValidGeoCoordinates]
        public ?string $geoCoordinates = null,
    ) {
    }
}
