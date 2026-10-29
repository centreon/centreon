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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceCollectionOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @implements TransformerInterface<ServiceListItemView, ServiceCollectionOutput>
 */
final readonly class ServiceCollectionOutputTransformer implements TransformerInterface
{
    public function __construct(
        private MediaUrlGenerator $mediaUrlGenerator,
    ) {
    }

    public function transform(mixed $from): ServiceCollectionOutput
    {
        $item = $from->item;

        $host = ['id' => $item->hostId->value, 'name' => $item->hostName];
        if ($from->hostIcon instanceof Media) {
            $host['icon'] = $this->icon($from->hostIcon);
        }

        return new ServiceCollectionOutput(
            id: $item->serviceId->value,
            name: $item->name,
            activated: $item->activated,
            host: $host,
            icon: $from->icon instanceof Media ? $this->icon($from->icon) : null,
            normalCheckInterval: $item->normalCheckInterval,
            retryCheckInterval: $item->retryCheckInterval,
            template: $item->templateId !== null && $item->templateName !== null
                ? ['id' => $item->templateId, 'name' => $item->templateName]
                : null,
        );
    }

    /**
     * @return array{id: int, name: string, url: string}
     */
    private function icon(Media $media): array
    {
        return ['id' => $media->id()->value, 'name' => $media->name->value, 'url' => $this->mediaUrlGenerator->generate($media)];
    }
}
