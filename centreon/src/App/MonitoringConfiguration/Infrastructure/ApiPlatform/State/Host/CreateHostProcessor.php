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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\MonitoringConfiguration\Application\Command\CreateHostCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodName;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneName;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreateHostInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCategoryOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostExtendedInformationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostGroupOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostIconOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostPollerOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostSchedulingOptionsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostSeverityOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostTemplateOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostTimePeriodOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostTimezoneOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\RelatedHostOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<CreateHostInput, HostResource>
 */
final readonly class CreateHostProcessor implements ProcessorInterface
{
    /**
     * @param TransformerInterface<Host, HostResource> $transformer
     */
    public function __construct(
        private CommandBus $commandBus,
        #[Autowire(service: HostResourceTransformer::class)]
        private TransformerInterface $transformer,
        private Security $security,
        private PollerRepository $pollerRepository,
        private HostGroupRepository $hostGroupRepository,
        private HostTemplateRepository $hostTemplateRepository,
        private HostCategoryRepository $hostCategoryRepository,
        private HostSeverityRepository $hostSeverityRepository,
        private TimezoneRepository $timezoneRepository,
        private HostRepository $hostRepository,
        private MediaRepository $mediaRepository,
        private MediaUrlGenerator $mediaUrlGenerator,
        private TimePeriodRepository $timePeriodRepository,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): HostResource
    {
        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);

        // Deduplicated as legacy does (AddHost::linkHostGroups()): a repeat is either a duplicate
        // key (host_template_relation) or a duplicate row reaching config generation.
        $hostGroupIds = $this->toIdCollection($data->hostGroupIds, HostGroupId::class);
        $templateIds = $this->toIdCollection($data->templateIds, HostTemplateId::class);
        $categoryIds = $this->toIdCollection($data->categoryIds, HostCategoryId::class);
        $parentHostIds = $this->toIdCollection($data->parentHostIds, HostId::class);
        $childHostIds = $this->toIdCollection($data->childHostIds, HostId::class);

        $extendedInformationsInput = $data->extendedInformations;
        $extendedInformations = new ExtendedInformations(
            noteUrl: $extendedInformationsInput?->noteUrl,
            note: $extendedInformationsInput?->note,
            actionUrl: $extendedInformationsInput?->actionUrl,
            iconId: $extendedInformationsInput?->iconId !== null ? new MediaId($extendedInformationsInput->iconId) : null,
            altIcon: $extendedInformationsInput?->altIcon,
            comment: $extendedInformationsInput?->comment,
            geoCoordinates: $extendedInformationsInput?->geoCoordinates !== null
                ? GeoCoordinates::fromString($extendedInformationsInput->geoCoordinates)
                : null,
        );

        $schedulingOptionsInput = $data->schedulingOptions;
        $schedulingOptions = new SchedulingOptions(
            checkTimeperiodId: $schedulingOptionsInput?->checkTimeperiodId !== null
                ? new TimePeriodId($schedulingOptionsInput->checkTimeperiodId)
                : null,
            maxCheckAttempts: $schedulingOptionsInput?->maxCheckAttempts,
            normalCheckInterval: $schedulingOptionsInput?->normalCheckInterval,
            retryCheckInterval: $schedulingOptionsInput?->retryCheckInterval,
            activeCheckEnabled: $this->triStateOrDefault($schedulingOptionsInput?->activeCheckEnabled),
            passiveCheckEnabled: $this->triStateOrDefault($schedulingOptionsInput?->passiveCheckEnabled),
        );

        $alias = $this->trimmedOrNull($data->alias);
        $snmpCommunity = $this->trimmedOrNull($data->snmpCommunity);

        $command = new CreateHostCommand(
            name: new HostName($data->name),
            address: new HostAddress($data->address),
            pollerId: new PollerId($data->pollerId),
            hostGroupIds: $hostGroupIds,
            creatorId: $credentialUser->credential->userId->value,
            viewerId: $credentialUser->credential->hasUnrestrictedResourceAccess() ? null : $credentialUser->credential->userId,
            alias: $alias !== null ? new HostAlias($alias) : null,
            templateIds: $templateIds,
            categoryIds: $categoryIds,
            parentHostIds: $parentHostIds,
            childHostIds: $childHostIds,
            snmpVersion: $data->snmpVersion,
            snmpCommunity: $snmpCommunity,
            timezoneId: $data->timezoneId !== null ? new TimezoneId($data->timezoneId) : null,
            severityId: $data->severityId !== null ? new HostSeverityId($data->severityId) : null,
            // Absent on Cloud, where linked services are always created (MON-208474).
            deployServicesFromTemplates: $data->createServicesLinkedToTemplates ?? true,
            extendedInformations: $extendedInformations,
            schedulingOptions: $schedulingOptions,
        );

        $host = $this->commandBus->execute($command);
        Assert::isInstanceOf($host, Host::class);

        return $this->toResource($host);
    }

    private function toResource(Host $host): HostResource
    {
        $pollerNames = $this->pollerRepository->findNamesByIds(new Collection([$host->pollerId], PollerId::class))->toArray();
        $groupNames = $this->hostGroupRepository->findNamesByIds($host->hostGroupIds)->toArray();
        $templateNames = $this->hostTemplateRepository->findNamesByIds($host->templateIds)->toArray();
        $categoryNames = $this->hostCategoryRepository->findNamesByIds($host->categoryIds)->toArray();
        $relatedHostNames = $this->hostRepository->findNamesByIds(new Collection(
            [...$host->parentHostIds->toArray(), ...$host->childHostIds->toArray()],
            HostId::class,
        ))->toArray();

        $resource = $this->transformer->transform($host);
        $resource->poller = new HostPollerOutput($host->pollerId->value, $pollerNames[$host->pollerId->value]->value ?? '');

        $resource->groups = [];
        foreach ($host->hostGroupIds as $groupId) {
            if (isset($groupNames[$groupId->value])) {
                $resource->groups[] = new HostGroupOutput($groupId->value, $groupNames[$groupId->value]->value);
            }
        }

        $resource->templates = [];
        foreach ($host->templateIds as $templateId) {
            if (isset($templateNames[$templateId->value])) {
                $resource->templates[] = new HostTemplateOutput($templateId->value, $templateNames[$templateId->value]->value);
            }
        }

        $resource->categories = [];
        foreach ($host->categoryIds as $categoryId) {
            if (isset($categoryNames[$categoryId->value])) {
                $resource->categories[] = new HostCategoryOutput($categoryId->value, $categoryNames[$categoryId->value]->value);
            }
        }

        $resource->parentHosts = $this->toRelatedHosts($host->parentHostIds, $relatedHostNames);
        $resource->childHosts = $this->toRelatedHosts($host->childHostIds, $relatedHostNames);

        if ($host->timezoneId instanceof TimezoneId) {
            $timezoneName = $this->timezoneRepository->findNameById($host->timezoneId);
            $resource->timezone = $timezoneName instanceof TimezoneName
                ? new HostTimezoneOutput($host->timezoneId->value, $timezoneName->value)
                : null;
        }

        if ($host->severityId instanceof HostSeverityId) {
            $severityName = $this->hostSeverityRepository->findNameById($host->severityId);
            $resource->severity = $severityName instanceof HostSeverityName
                ? new HostSeverityOutput($host->severityId->value, $severityName->value)
                : null;
        }

        $resource->extendedInformations = new HostExtendedInformationsOutput(
            noteUrl: $host->extendedInformations?->noteUrl,
            note: $host->extendedInformations?->note,
            actionUrl: $host->extendedInformations?->actionUrl,
            icon: $this->resolveIcon($host->extendedInformations?->iconId),
            altIcon: $host->extendedInformations?->altIcon,
            comment: $host->extendedInformations?->comment,
            geoCoordinates: $host->extendedInformations?->geoCoordinates instanceof GeoCoordinates
                ? (string) $host->extendedInformations->geoCoordinates
                : null,
        );
        $resource->schedulingOptions = new HostSchedulingOptionsOutput(
            checkPeriod: $this->resolveCheckPeriod($host->schedulingOptions->checkTimeperiodId),
            maxCheckAttempts: $host->schedulingOptions->maxCheckAttempts,
            normalCheckInterval: $host->schedulingOptions->normalCheckInterval,
            retryCheckInterval: $host->schedulingOptions->retryCheckInterval,
            activeCheckEnabled: $this->isCloudPlatform ? null : $host->schedulingOptions->activeCheckEnabled,
            passiveCheckEnabled: $this->isCloudPlatform ? null : $host->schedulingOptions->passiveCheckEnabled,
        );

        return $resource;
    }

    private function triStateOrDefault(?TriStateEnum $value): TriStateEnum
    {
        return $value ?? TriStateEnum::UseDefault;
    }

    private function resolveCheckPeriod(?TimePeriodId $checkTimeperiodId): ?HostTimePeriodOutput
    {
        if (! $checkTimeperiodId instanceof TimePeriodId) {
            return null;
        }

        $name = $this->timePeriodRepository
            ->findNamesByIds(new Collection([$checkTimeperiodId], TimePeriodId::class))
            ->toArray()[$checkTimeperiodId->value] ?? null;

        return $name instanceof TimePeriodName
            ? new HostTimePeriodOutput($checkTimeperiodId->value, $name->value)
            : null;
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

    private function trimmedOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @template T of object
     *
     * @param list<int> $ids
     * @param class-string<T> $className
     *
     * @return Collection<T>
     */
    private function toIdCollection(array $ids, string $className): Collection
    {
        return new Collection(
            array_map(static fn (int $id): object => new $className($id), array_values(array_unique($ids))),
            $className,
        );
    }

    private function resolveIcon(?MediaId $iconId): ?HostIconOutput
    {
        if (! $iconId instanceof MediaId) {
            return null;
        }

        $icon = $this->mediaRepository->findByIds(new Collection([$iconId], MediaId::class))->toArray()[$iconId->value] ?? null;

        return $icon instanceof Media
            ? new HostIconOutput($icon->id()->value, $icon->name->value, $this->mediaUrlGenerator->generate($icon))
            : null;
    }
}
