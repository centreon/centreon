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

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
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
use App\MonitoringConfiguration\Domain\Service\InheritedHostMacrosResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\DataProcessingOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCategoryOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCheckCommandOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostCheckOptionsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostEventHandlerCommandOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostExtendedInformationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostGroupOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostIconOutput;
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
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds the HostResource a host endpoint answers with (GetHost, PutHost), resolving the ids the
 * aggregate carries into the named `{id, name}` outputs the contract exposes, in the shape of the
 * CreateHost response. A restricted viewer's view is narrowed to what they may access, so an
 * out-of-scope association is never surfaced, whichever endpoint answers. Secrets (snmpCommunity,
 * password macros) are never part of HostResource and so are never surfaced.
 */
final readonly class HostResourceBuilder
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
        private ResourceAccessRepository $resourceAccessRepository,
        private HostMacroTransformer $macroTransformer,
        private InheritedHostMacrosResolver $inheritedHostMacrosResolver,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    /**
     * @param ?UserId $viewerId null when the viewer is unrestricted (admin)
     */
    public function build(Host $host, ?UserId $viewerId): HostResource
    {
        $pollerName = $this->pollerRepository->findNamesByIds(new Collection([$host->pollerId], PollerId::class))->toArray();

        // The aggregate carries the host's full associations; here, on the read side, a restricted
        // viewer's view is narrowed to what they may access — the host groups, categories and
        // severity are ACL-scoped (as they are on write, CreateHostCommandHandler), so an
        // out-of-scope one is dropped from the response rather than surfaced (no existence leak).
        // The id list is scoped first; the name lookup then runs on a list already known to be
        // accessible. Keeping this in the read model, not in findOne(), leaves the aggregate whole
        // for the write paths that also load it.
        $groupIds = $this->scopeToAccessible(
            $host->hostGroupIds,
            $viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleHostGroupIds($viewerId) : null,
            HostGroupId::class,
        );
        $groupNames = $this->hostGroupRepository->findNamesByIds($groupIds)->toArray();

        $groups = [];
        foreach ($groupIds as $groupId) {
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

        // Parent and child hosts are ACL-scoped like host groups above, as the legacy host form hides
        // the out-of-scope ones (CentreonHost::getObjectForSelect2()).
        $accessibleHostIds = $viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleHostIds($viewerId) : null;
        $parentHostIds = $this->scopeToAccessible($host->parentHostIds, $accessibleHostIds, HostId::class);
        $childHostIds = $this->scopeToAccessible($host->childHostIds, $accessibleHostIds, HostId::class);
        $relatedHostNames = $this->hostRepository->findNamesByIds(new Collection(
            [...$parentHostIds->toArray(), ...$childHostIds->toArray()],
            HostId::class,
        ))->toArray();
        $resource->parentHosts = $this->toRelatedHosts($parentHostIds, $relatedHostNames);
        $resource->childHosts = $this->toRelatedHosts($childHostIds, $relatedHostNames);

        // Categories are ACL-scoped like host groups above: out-of-scope ones are hidden here.
        $categoryIds = $this->scopeToAccessible(
            $host->categoryIds,
            $viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleHostCategoryIds($viewerId) : null,
            HostCategoryId::class,
        );
        $categoryNames = $this->hostCategoryRepository->findNamesByIds($categoryIds)->toArray();
        $resource->categories = [];
        foreach ($categoryIds as $categoryId) {
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

        // Severity is ACL-scoped too: an out-of-scope severity is hidden (left null) from a
        // restricted viewer rather than surfaced.
        if ($host->severityId instanceof HostSeverityId && $this->isAccessible(
            $host->severityId,
            $viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleHostSeverityIds($viewerId) : null,
        )) {
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
        // Every macro the host effectively has, as CreateHost returns them: its own (hydrated with
        // their ids by findOne), then those it still inherits, each with the id + parent a client
        // resends to change it.
        $macroOutputs = array_map(
            $this->macroTransformer->transform(...),
            $this->inheritedHostMacrosResolver
                ->resolve($host->templateIds, $host->checkOptions->checkCommandId)
                ->effectiveWith($host->checkOptions->macros),
        );
        $resource->checkOptions = new HostCheckOptionsOutput($checkCommandOutput, $host->checkOptions->args, $macroOutputs);

        // Cloud handles notifications through a different model; CreateHost omits the block there, so
        // GET must too, keeping the two bodies identical. The repository stays platform-agnostic and
        // always hydrates what is persisted, so the Cloud decision is applied here (not in findOne).
        $resource->notifications = $this->isCloudPlatform
            ? null
            : $this->notificationsTransformer->transform($this->scopeNotifications($host->notifications, $viewerId));

        return $resource;
    }

    /**
     * Notification contacts and contact groups are ACL-scoped like the other associations, as the
     * legacy host form hides the out-of-scope ones (CentreonContact::getObjectForSelect2(),
     * CentreonContactgroup::getObjectForSelect2()).
     */
    private function scopeNotifications(?Notifications $notifications, ?UserId $viewerId): ?Notifications
    {
        if (! $notifications instanceof Notifications || ! $viewerId instanceof UserId) {
            return $notifications;
        }

        return $notifications->withContacts(
            $this->scopeToAccessible(
                $notifications->contactIds,
                $this->resourceAccessRepository->findAccessibleContactIds($viewerId),
                NotificationContactId::class,
            ),
            $this->scopeToAccessible(
                $notifications->contactGroupIds,
                $this->resourceAccessRepository->findAccessibleContactGroupIds($viewerId),
                ContactGroupId::class,
            ),
        );
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
     * Keeps only the ids the viewer may access, mirroring the per-viewer ACL checks enforced on
     * write (host groups, categories, severity, parent/child hosts, notification contacts). A null
     * $accessible means no restriction applies (admin, or an unrestricted dimension), so the ids
     * pass through unchanged. The returned list is known to be in-scope, so the follow-up name
     * lookup needs no further filtering.
     *
     * @template T of AggregateRootId
     *
     * @param Collection<T> $ids
     * @param Collection<T>|null $accessible
     * @param class-string<T> $className
     *
     * @return Collection<T>
     */
    private function scopeToAccessible(Collection $ids, ?Collection $accessible, string $className): Collection
    {
        if (! $accessible instanceof Collection) {
            return $ids;
        }

        $accessibleValues = array_map(static fn (AggregateRootId $id): int => $id->value, $accessible->toArray());

        return new Collection(
            array_values(array_filter(
                $ids->toArray(),
                static fn (AggregateRootId $id): bool => in_array($id->value, $accessibleValues, true),
            )),
            $className,
        );
    }

    /**
     * Single-id counterpart of {@see self::scopeToAccessible()} for the host's severity. A null
     * $accessible means no restriction applies, so the id is accessible.
     *
     * @template T of AggregateRootId
     *
     * @param T $id
     * @param Collection<T>|null $accessible
     */
    private function isAccessible(AggregateRootId $id, ?Collection $accessible): bool
    {
        if (! $accessible instanceof Collection) {
            return true;
        }

        return in_array(
            $id->value,
            array_map(static fn (AggregateRootId $accessibleId): int => $accessibleId->value, $accessible->toArray()),
            true,
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
