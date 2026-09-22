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
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
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
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CheckOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreateHostInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\DataProcessingInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\DataProcessingOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCategoryOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCheckCommandOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCheckOptionsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostEventHandlerCommandOutput;
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
        private CommandRepository $commandRepository,
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

        // Deduplicated the same way legacy does (AddHost::linkHostGroups()): a client repeating an
        // id is tolerated, not rejected, but must not produce one hostgroup_relation row per
        // repetition.
        $hostGroupIds = new Collection(
            array_map(static fn (int $id): HostGroupId => new HostGroupId($id), array_unique($data->hostGroupIds)),
            HostGroupId::class,
        );

        $dpInput = $data->dataProcessing ?? new DataProcessingInput();
        $dataProcessing = new DataProcessing(
            checkFreshness: $dpInput->checkFreshness ?? TriStateEnum::UseDefault,
            flapDetectionEnabled: $dpInput->flapDetectionEnabled ?? TriStateEnum::UseDefault,
            eventHandlerEnabled: $dpInput->eventHandlerEnabled ?? TriStateEnum::UseDefault,
            acknowledgmentTimeout: $dpInput->acknowledgmentTimeout,
            freshnessThreshold: $dpInput->freshnessThreshold,
            lowFlapThreshold: $dpInput->lowFlapThreshold,
            highFlapThreshold: $dpInput->highFlapThreshold,
            eventHandlerCommandId: $dpInput->eventHandlerCommandId !== null ? new CommandId($dpInput->eventHandlerCommandId) : null,
            eventHandlerArgs: array_values($dpInput->eventHandlerArgs),
        );

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

        $checkOptionsInput = $data->checkOptions;
        $checkOptions = new CheckOptions(
            $checkOptionsInput?->commandId !== null ? new CommandId($checkOptionsInput->commandId) : null,
            $checkOptionsInput instanceof CheckOptionsInput ? array_values($checkOptionsInput->args) : [],
        );

        $alias = $this->trimmedOrNull($data->alias);

        $command = new CreateHostCommand(
            name: new HostName($data->name),
            address: new HostAddress($data->address),
            pollerId: new PollerId($data->pollerId),
            hostGroupIds: $hostGroupIds,
            dataProcessing: $dataProcessing,
            creatorId: $credentialUser->credential->userId->value,
            viewerId: $credentialUser->credential->hasUnrestrictedResourceAccess() ? null : $credentialUser->credential->userId,
            alias: $alias !== null ? new HostAlias($alias) : null,
            snmpVersion: $data->snmpVersion,
            templateIds: $this->toIdCollection($data->templateIds, HostTemplateId::class),
            categoryIds: $this->toIdCollection($data->categoryIds, HostCategoryId::class),
            parentHostIds: $this->toIdCollection($data->parentHostIds, HostId::class),
            childHostIds: $this->toIdCollection($data->childHostIds, HostId::class),
            snmpCommunity: $this->trimmedOrNull($data->snmpCommunity),
            timezoneId: $data->timezoneId !== null ? new TimezoneId($data->timezoneId) : null,
            severityId: $data->severityId !== null ? new HostSeverityId($data->severityId) : null,
            // Absent on Cloud, where linked services are always created.
            deployServicesFromTemplates: $data->createServicesLinkedToTemplates ?? true,
            extendedInformations: $extendedInformations,
            schedulingOptions: $schedulingOptions,
            checkOptions: $checkOptions,
        );

        $host = $this->commandBus->execute($command);
        Assert::isInstanceOf($host, Host::class);

        $pollerName = $this->pollerRepository->findNamesByIds(new Collection([$host->pollerId], PollerId::class))->toArray();
        $groupNames = $this->hostGroupRepository->findNamesByIds($hostGroupIds)->toArray();

        $groups = [];
        foreach ($host->hostGroupIds as $groupId) {
            if (isset($groupNames[$groupId->value])) {
                $groups[] = new HostGroupOutput($groupId->value, $groupNames[$groupId->value]->value);
            }
        }

        $icon = $this->resolveIcon($host->extendedInformations?->iconId);

        $checkCommandOutput = null;
        if ($host->checkOptions->checkCommandId instanceof CommandId) {
            // The command exists (the handler already validated it), so this resolves it purely to
            // surface its name in the response, the same way the poller name is resolved above.
            $checkCommand = $this->commandRepository->getById($host->checkOptions->checkCommandId);
            $checkCommandOutput = new HostCheckCommandOutput($checkCommand->id()->value, $checkCommand->name->value);
        }

        $resource = $this->transformer->transform($host);
        $resource->poller = new HostPollerOutput($host->pollerId->value, $pollerName[$host->pollerId->value]->value ?? '');
        $resource->groups = $groups;
        $resource->dataProcessing = $this->buildDataProcessingOutput($host->dataProcessing);

        $templateNames = $this->hostTemplateRepository->findNamesByIds($host->templateIds)->toArray();
        $resource->templates = [];
        foreach ($host->templateIds as $templateId) {
            if (isset($templateNames[$templateId->value])) {
                $resource->templates[] = new HostTemplateOutput($templateId->value, $templateNames[$templateId->value]->value);
            }
        }

        $relatedHostNames = $this->hostRepository->findNamesByIds(new Collection(
            [...$host->parentHostIds->toArray(), ...$host->childHostIds->toArray()],
            HostId::class,
        ))->toArray();
        $resource->parentHosts = $this->toRelatedHosts($host->parentHostIds, $relatedHostNames);
        $resource->childHosts = $this->toRelatedHosts($host->childHostIds, $relatedHostNames);

        $categoryNames = $this->hostCategoryRepository->findNamesByIds($host->categoryIds)->toArray();
        $resource->categories = [];
        foreach ($host->categoryIds as $categoryId) {
            if (isset($categoryNames[$categoryId->value])) {
                $resource->categories[] = new HostCategoryOutput($categoryId->value, $categoryNames[$categoryId->value]->value);
            }
        }

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
            icon: $icon,
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
        $resource->checkOptions = new HostCheckOptionsOutput($checkCommandOutput, $host->checkOptions->args);

        return $resource;
    }

    private function buildDataProcessingOutput(DataProcessing $dataProcessing): DataProcessingOutput
    {
        $eventHandler = null;
        if ($dataProcessing->eventHandlerCommandId instanceof CommandId) {
            $command = $this->commandRepository->getById($dataProcessing->eventHandlerCommandId);
            $eventHandler = new HostEventHandlerCommandOutput($command->id()->value, $command->name->value);
        }

        // On a Cloud platform the on-premise-only members are not part of the contract.
        return new DataProcessingOutput(
            checkFreshness: $dataProcessing->checkFreshness,
            freshnessThreshold: $dataProcessing->freshnessThreshold,
            eventHandlerEnabled: $dataProcessing->eventHandlerEnabled,
            eventHandler: $eventHandler,
            acknowledgmentTimeout: $this->isCloudPlatform ? null : $dataProcessing->acknowledgmentTimeout,
            flapDetectionEnabled: $this->isCloudPlatform ? null : $dataProcessing->flapDetectionEnabled,
            lowFlapThreshold: $this->isCloudPlatform ? null : $dataProcessing->lowFlapThreshold,
            highFlapThreshold: $this->isCloudPlatform ? null : $dataProcessing->highFlapThreshold,
            eventHandlerArgs: $this->isCloudPlatform ? [] : $dataProcessing->eventHandlerArgs,
        );
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
        // Deduplicated as legacy does: hostcategories_relation has no unique key, so a repeat
        // would reach config generation twice.
        return new Collection(
            array_map(static fn (int $id): object => new $className($id), array_values(array_unique($ids))),
            $className,
        );
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
