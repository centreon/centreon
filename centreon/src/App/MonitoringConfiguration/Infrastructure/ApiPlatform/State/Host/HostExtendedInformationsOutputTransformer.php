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
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostExtendedInformationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostIconOutput;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements TransformerInterface<ExtendedInformations, HostExtendedInformationsOutput>
 */
final readonly class HostExtendedInformationsOutputTransformer implements TransformerInterface
{
    /**
     * @param TransformerInterface<Media, HostIconOutput> $iconTransformer
     */
    public function __construct(
        private MediaRepository $mediaRepository,
        #[Autowire(service: HostIconOutputTransformer::class)]
        private TransformerInterface $iconTransformer,
    ) {
    }

    public function transform(mixed $from, array $extraData = []): HostExtendedInformationsOutput
    {
        return new HostExtendedInformationsOutput(
            noteUrl: $from->noteUrl,
            note: $from->note,
            actionUrl: $from->actionUrl,
            icon: $this->resolveIcon($from->iconId),
            altIcon: $from->altIcon,
            comment: $from->comment,
            geoCoordinates: $from->geoCoordinates instanceof GeoCoordinates ? (string) $from->geoCoordinates : null,
        );
    }

    private function resolveIcon(?MediaId $iconId): ?HostIconOutput
    {
        if (! $iconId instanceof MediaId) {
            return null;
        }

        $icon = $this->mediaRepository->findByIds(new Collection([$iconId], MediaId::class))->toArray()[$iconId->value] ?? null;

        return $icon instanceof Media ? $this->iconTransformer->transform($icon) : null;
    }
}
