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
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCollectionOutput;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves the related names of the whole list at once, one query per relation, then delegates
 * each host to {@see HostCollectionOutputTransformer}.
 *
 * @phpstan-import-type ExtraDataTypeAlias from HostCollectionOutputTransformer
 *
 * @implements TransformerInterface<list<Host>, list<HostCollectionOutput>>
 */
final readonly class HostCollectionOutputListTransformer implements TransformerInterface
{
    /**
     * @param TransformerInterface<Host, HostCollectionOutput, ExtraDataTypeAlias> $itemTransformer
     */
    public function __construct(
        private PollerRepository $pollerRepository,
        private HostTemplateRepository $hostTemplateRepository,
        private MediaRepository $mediaRepository,
        #[Autowire(service: HostCollectionOutputTransformer::class)]
        private TransformerInterface $itemTransformer,
    ) {
    }

    public function transform(mixed $from, array $extraData = []): array
    {
        if ($from === []) {
            return [];
        }

        /** @var array<int, PollerId> $pollerIds */
        $pollerIds = [];
        /** @var array<int, HostTemplateId> $templateIds */
        $templateIds = [];
        /** @var array<int, MediaId> $iconIds */
        $iconIds = [];
        foreach ($from as $host) {
            $pollerIds[$host->pollerId->value] = $host->pollerId;
            foreach ($host->templateIds as $templateId) {
                $templateIds[$templateId->value] = $templateId;
            }
            if ($host->extendedInformations?->iconId !== null) {
                $iconIds[$host->extendedInformations->iconId->value] = $host->extendedInformations->iconId;
            }
        }

        /** @var ExtraDataTypeAlias $lookups indexed by id, as the repositories return them */
        $lookups = [
            'pollerNames' => $this->pollerRepository->findNamesByIds(new Collection(array_values($pollerIds), PollerId::class))->toArray(),
            'templateNames' => $this->hostTemplateRepository->findNamesByIds(new Collection(array_values($templateIds), HostTemplateId::class))->toArray(),
            'icons' => $this->mediaRepository->findByIds(new Collection(array_values($iconIds), MediaId::class))->toArray(),
        ];

        return array_map(fn (Host $host): HostCollectionOutput => $this->itemTransformer->transform($host, $lookups), $from);
    }
}
