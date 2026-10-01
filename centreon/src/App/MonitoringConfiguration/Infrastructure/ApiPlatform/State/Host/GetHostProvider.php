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
use ApiPlatform\State\ProviderInterface;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodName;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneName;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
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
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\DataProcessingOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCategoryOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCheckCommandOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCheckOptionsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostEventHandlerCommandOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostExtendedInformationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostGroupOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostIconOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostMacroOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostNotificationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostPollerOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostSchedulingOptionsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostSeverityOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostTemplateOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostTimezoneOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\RelatedHostOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\TimePeriod\TimePeriodResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * Returns the full detail of one host. The response body is identical in shape to the CreateHost
 * *response* (not its request): the enrichment below mirrors CreateHostProcessor, resolving the ids
 * the aggregate carries into the named `{id, name}` outputs the contract exposes. The asymmetry is
 * intentional — CreateHost's *request* takes bare ids (`poller_id`, `template_id`, …), while both
 * its response and this read expose the richer objects (`poller: {id, name}`, …) the host form
 * needs. Secrets (snmpCommunity, password macros) are never part of HostResource and so are never
 * surfaced.
 *
 * @implements ProviderInterface<HostResource>
 */
final readonly class GetHostProvider implements ProviderInterface
{
    /**
     * @param TransformerInterface<Host, HostResource> $transformer
     * @param TransformerInterface<?Notifications, ?HostNotificationsOutput> $notificationsTransformer
     */
    public function __construct(
        #[Autowire(service: HostResourceTransformer::class)]
        private TransformerInterface $transformer,
        #[Autowire(service: HostNotificationsTransformer::class)]
        private TransformerInterface $notificationsTransformer,
        private Security $security,
        private HostRepository $hostRepository,
        private PollerRepository $pollerRepository,
        private HostGroupRepository $hostGroupRepository,
        private CommandRepository $commandRepository,
        private HostTemplateRepository $hostTemplateRepository,
        private HostCategoryRepository $hostCategoryRepository,
        private HostSeverityRepository $hostSeverityRepository,
        private TimezoneRepository $timezoneRepository,
        private MediaRepository $mediaRepository,
        private MediaUrlGenerator $mediaUrlGenerator,
        private TimePeriodRepository $timePeriodRepository,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): HostResource
    {
        Assert::integer($uriVariables['id']);
        $hostId = new HostId($uriVariables['id']);

        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);

        // Admin (unrestricted) sees every host; a restricted viewer is scoped in the query, and an
        // out-of-scope host comes back null, indistinguishable from a missing one (no existence leak).
        $viewerId = $credentialUser->credential->hasUnrestrictedResourceAccess()
            ? null
            : $credentialUser->credential->userId;

        $host = $this->hostRepository->findOne($hostId, $viewerId);
        if (! $host instanceof Host) {
            throw new HostNotFoundException([$hostId->value], 'id');
        }

        return $this->buildResource($host);
    }

    private function buildResource(Host $host): HostResource
    {
        $pollerName = $this->pollerRepository->findNamesByIds(new Collection([$host->pollerId], PollerId::class))->toArray();
        $groupNames = $this->hostGroupRepository->findNamesByIds($host->hostGroupIds)->toArray();

        $groups = [];
        foreach ($host->hostGroupIds as $groupId) {
            if (isset($groupNames[$groupId->value])) {
                $groups[] = new HostGroupOutput($groupId->value, $groupNames[$groupId->value]->value);
            }
        }

        $checkCommandOutput = null;
        if ($host->checkOptions->checkCommandId instanceof CommandId) {
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

        // Nullable sub-object, like `severity`/`timezone`: left null (and so omitted) when every
        // field is empty, rather than emitted as an all-null object (which the serializer collapses
        // to an invalid `[]`). Mirrors CreateHostProcessor so the two bodies stay identical.
        $resource->extendedInformations = null;
        $extended = $host->extendedInformations;
        if ($extended instanceof ExtendedInformations) {
            $icon = $this->resolveIcon($extended->iconId);
            $geoCoordinates = $extended->geoCoordinates instanceof GeoCoordinates
                ? (string) $extended->geoCoordinates
                : null;
            if ($extended->noteUrl !== null
                || $extended->note !== null
                || $extended->actionUrl !== null
                || $icon instanceof HostIconOutput
                || $extended->altIcon !== null
                || $extended->comment !== null
                || $geoCoordinates !== null
            ) {
                $resource->extendedInformations = new HostExtendedInformationsOutput(
                    noteUrl: $extended->noteUrl,
                    note: $extended->note,
                    actionUrl: $extended->actionUrl,
                    icon: $icon,
                    altIcon: $extended->altIcon,
                    comment: $extended->comment,
                    geoCoordinates: $geoCoordinates,
                );
            }
        }
        $resource->schedulingOptions = new HostSchedulingOptionsOutput(
            checkPeriod: $this->resolveCheckPeriod($host->schedulingOptions->checkTimeperiodId),
            maxCheckAttempts: $host->schedulingOptions->maxCheckAttempts,
            normalCheckInterval: $host->schedulingOptions->normalCheckInterval,
            retryCheckInterval: $host->schedulingOptions->retryCheckInterval,
            activeCheckEnabled: $this->isCloudPlatform ? null : $host->schedulingOptions->activeCheckEnabled,
            passiveCheckEnabled: $this->isCloudPlatform ? null : $host->schedulingOptions->passiveCheckEnabled,
        );
        $macroOutputs = array_map(
            static fn (HostMacro $macro): HostMacroOutput => new HostMacroOutput(
                $macro->name->value,
                // A password macro's stored value is a vault reference (or secret) — never echoed.
                $macro->isPassword ? null : $macro->value,
                $macro->isPassword,
                $macro->description,
            ),
            $host->checkOptions->macros,
        );
        $resource->checkOptions = new HostCheckOptionsOutput($checkCommandOutput, $host->checkOptions->args, $macroOutputs);

        // Cloud handles notifications through a different model; CreateHost omits the block there, so
        // GET must too, keeping the two bodies identical. The repository stays platform-agnostic and
        // always hydrates what is persisted, so the Cloud decision is applied here (not in findOne).
        $resource->notifications = $this->isCloudPlatform
            ? null
            : $this->notificationsTransformer->transform($host->notifications);

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

    private function resolveCheckPeriod(?TimePeriodId $checkTimeperiodId): ?TimePeriodResource
    {
        if (! $checkTimeperiodId instanceof TimePeriodId) {
            return null;
        }

        $name = $this->timePeriodRepository
            ->findNamesByIds(new Collection([$checkTimeperiodId], TimePeriodId::class))
            ->toArray()[$checkTimeperiodId->value] ?? null;

        return $name instanceof TimePeriodName
            ? new TimePeriodResource($checkTimeperiodId->value, $name->value)
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
