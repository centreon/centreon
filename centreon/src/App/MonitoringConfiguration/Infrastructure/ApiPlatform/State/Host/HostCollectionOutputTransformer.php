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

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCollectionOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostIconOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostPollerOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostTemplateOutput;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * Takes the related names already resolved for the whole page, so that transforming a page of
 * hosts costs one query per relation: see {@see HostCollectionOutputListTransformer}.
 *
 * Lookups are indexed by id; a name missing from them is left out, as when the related row is gone.
 *
 * @phpstan-type ExtraDataTypeAlias array{
 *     pollerNames?: array<int, PollerName>,
 *     templateNames?: array<int, HostTemplateName>,
 *     icons?: array<int, Media>,
 * }
 *
 * @implements TransformerInterface<Host, HostCollectionOutput, ExtraDataTypeAlias>
 */
final readonly class HostCollectionOutputTransformer implements TransformerInterface
{
    /**
     * @param TransformerInterface<Media, HostIconOutput> $iconTransformer
     */
    public function __construct(
        #[Autowire(service: HostIconOutputTransformer::class)]
        private TransformerInterface $iconTransformer,
    ) {
    }

    public function transform(mixed $from, array $extraData = []): HostCollectionOutput
    {
        Assert::keyExists($extraData, 'pollerNames');
        Assert::keyExists($extraData, 'templateNames');
        Assert::keyExists($extraData, 'icons');

        $templates = [];
        foreach ($from->templateIds as $templateId) {
            if (isset($extraData['templateNames'][$templateId->value])) {
                $templates[] = new HostTemplateOutput($templateId->value, $extraData['templateNames'][$templateId->value]->value);
            }
        }

        $iconId = $from->extendedInformations?->iconId;
        $icon = $iconId !== null ? $extraData['icons'][$iconId->value] ?? null : null;

        return new HostCollectionOutput(
            id: $from->id()->value,
            name: $from->name->value,
            alias: $from->alias?->value,
            address: $from->address->value,
            activated: $from->activated,
            poller: new HostPollerOutput($from->pollerId->value, $extraData['pollerNames'][$from->pollerId->value]->value ?? ''),
            templates: $templates,
            icon: $icon instanceof Media ? $this->iconTransformer->transform($icon) : null,
        );
    }
}
