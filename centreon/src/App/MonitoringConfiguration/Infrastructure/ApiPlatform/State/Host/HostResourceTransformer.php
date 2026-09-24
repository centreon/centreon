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

use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneName;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\DataProcessingOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCategoryOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostExtendedInformationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostGroupOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostPollerOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostSchedulingOptionsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostSeverityOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostTemplateOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostTimezoneOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\RelatedHostOutput;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves every name the resource exposes by itself, one lookup per relation: meant for a single
 * host. A page of hosts goes through {@see HostCollectionOutputListTransformer}, which batches them.
 *
 * @implements TransformerInterface<Host, HostResource>
 */
final readonly class HostResourceTransformer implements TransformerInterface
{
    /**
     * @param TransformerInterface<DataProcessing, DataProcessingOutput> $dataProcessingTransformer
     * @param TransformerInterface<ExtendedInformations, HostExtendedInformationsOutput> $extendedInformationsTransformer
     * @param TransformerInterface<SchedulingOptions, HostSchedulingOptionsOutput> $schedulingOptionsTransformer
     */
    public function __construct(
        private PollerRepository $pollerRepository,
        private HostGroupRepository $hostGroupRepository,
        private HostTemplateRepository $hostTemplateRepository,
        private HostRepository $hostRepository,
        private HostCategoryRepository $hostCategoryRepository,
        private TimezoneRepository $timezoneRepository,
        private HostSeverityRepository $hostSeverityRepository,
        #[Autowire(service: DataProcessingOutputTransformer::class)]
        private TransformerInterface $dataProcessingTransformer,
        #[Autowire(service: HostExtendedInformationsOutputTransformer::class)]
        private TransformerInterface $extendedInformationsTransformer,
        #[Autowire(service: HostSchedulingOptionsOutputTransformer::class)]
        private TransformerInterface $schedulingOptionsTransformer,
    ) {
    }

    public function transform(mixed $from, array $extraData = []): HostResource
    {
        $pollerNames = $this->pollerRepository->findNamesByIds(new Collection([$from->pollerId], PollerId::class))->toArray();
        $groupNames = $this->hostGroupRepository->findNamesByIds($from->hostGroupIds)->toArray();
        $templateNames = $this->hostTemplateRepository->findNamesByIds($from->templateIds)->toArray();
        $categoryNames = $this->hostCategoryRepository->findNamesByIds($from->categoryIds)->toArray();
        $relatedHostNames = $this->hostRepository->findNamesByIds(new Collection(
            [...$from->parentHostIds->toArray(), ...$from->childHostIds->toArray()],
            HostId::class,
        ))->toArray();

        $groups = [];
        foreach ($from->hostGroupIds as $groupId) {
            if (isset($groupNames[$groupId->value])) {
                $groups[] = new HostGroupOutput($groupId->value, $groupNames[$groupId->value]->value);
            }
        }

        $templates = [];
        foreach ($from->templateIds as $templateId) {
            if (isset($templateNames[$templateId->value])) {
                $templates[] = new HostTemplateOutput($templateId->value, $templateNames[$templateId->value]->value);
            }
        }

        $categories = [];
        foreach ($from->categoryIds as $categoryId) {
            if (isset($categoryNames[$categoryId->value])) {
                $categories[] = new HostCategoryOutput($categoryId->value, $categoryNames[$categoryId->value]->value);
            }
        }

        return new HostResource(
            id: $from->id()->value,
            name: $from->name->value,
            alias: $from->alias?->value,
            address: $from->address->value,
            activated: $from->activated,
            snmpVersion: $from->snmpVersion,
            poller: new HostPollerOutput($from->pollerId->value, $pollerNames[$from->pollerId->value]->value ?? ''),
            templates: $templates,
            groups: $groups,
            dataProcessing: $this->dataProcessingTransformer->transform($from->dataProcessing),
            categories: $categories,
            parentHosts: $this->toRelatedHosts($from->parentHostIds, $relatedHostNames),
            childHosts: $this->toRelatedHosts($from->childHostIds, $relatedHostNames),
            timezone: $this->resolveTimezone($from->timezoneId),
            severity: $this->resolveSeverity($from->severityId),
            extendedInformations: $this->extendedInformationsTransformer->transform($from->extendedInformations ?? new ExtendedInformations()),
            schedulingOptions: $this->schedulingOptionsTransformer->transform($from->schedulingOptions),
        );
    }

    private function resolveTimezone(?TimezoneId $timezoneId): ?HostTimezoneOutput
    {
        if (! $timezoneId instanceof TimezoneId) {
            return null;
        }

        $name = $this->timezoneRepository->findNameById($timezoneId);

        return $name instanceof TimezoneName ? new HostTimezoneOutput($timezoneId->value, $name->value) : null;
    }

    private function resolveSeverity(?HostSeverityId $severityId): ?HostSeverityOutput
    {
        if (! $severityId instanceof HostSeverityId) {
            return null;
        }

        $name = $this->hostSeverityRepository->findNameById($severityId);

        return $name instanceof HostSeverityName ? new HostSeverityOutput($severityId->value, $name->value) : null;
    }

    /**
     * @param Collection<HostId> $hostIds
     * @param array<array-key, HostName> $names indexed by host id
     *
     * @return list<RelatedHostOutput>
     */
    private function toRelatedHosts(Collection $hostIds, array $names): array
    {
        $related = [];
        foreach ($hostIds as $hostId) {
            if (isset($names[$hostId->value])) {
                $related[] = new RelatedHostOutput($hostId->value, $names[$hostId->value]->value);
            }
        }

        return $related;
    }
}
