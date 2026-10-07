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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostExtendedInformationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostIconOutput;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @phpstan-type ExtraDataTypeAlias array{icons?: array<int, Media>}
 *
 * @implements TransformerInterface<ExtendedInformations, HostExtendedInformationsOutput, ExtraDataTypeAlias>
 */
final readonly class HostExtendedInformationsOutputTransformer implements TransformerInterface
{
    /**
     * @param TransformerInterface<Media, HostIconOutput> $iconTransformer
     */
    public function __construct(
        #[Autowire(service: HostIconOutputTransformer::class)]
        private TransformerInterface $iconTransformer,
    ) {
    }

    public function transform(mixed $from, array $extraData = []): HostExtendedInformationsOutput
    {
        Assert::keyExists($extraData, 'icons');

        $icon = $from->iconId !== null ? $extraData['icons'][$from->iconId->value] ?? null : null;

        return new HostExtendedInformationsOutput(
            noteUrl: $from->noteUrl,
            note: $from->note,
            actionUrl: $from->actionUrl,
            icon: $icon instanceof Media ? $this->iconTransformer->transform($icon) : null,
            altIcon: $from->altIcon,
            comment: $from->comment,
            geoCoordinates: $from->geoCoordinates instanceof GeoCoordinates ? (string) $from->geoCoordinates : null,
        );
    }
}
