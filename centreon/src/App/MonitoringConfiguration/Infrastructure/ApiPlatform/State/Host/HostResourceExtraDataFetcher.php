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
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneName;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\MonitoringConfiguration\Domain\Service\InheritedHostMacrosResolver;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Collection;

/**
 * Fetches everything HostResourceTransformer needs besides the host itself, so that the
 * transformer stays a pure mapping.
 *
 * @phpstan-import-type ExtraDataTypeAlias from HostResourceTransformer
 */
final readonly class HostResourceExtraDataFetcher
{
    public function __construct(
        private PollerRepository $pollerRepository,
        private HostGroupRepository $hostGroupRepository,
        private HostTemplateRepository $hostTemplateRepository,
        private HostCategoryRepository $hostCategoryRepository,
        private HostRepository $hostRepository,
        private TimezoneRepository $timezoneRepository,
        private HostSeverityRepository $hostSeverityRepository,
        private CommandRepository $commandRepository,
        private TimePeriodRepository $timePeriodRepository,
        private MediaRepository $mediaRepository,
        private NotificationContactRepository $notificationContactRepository,
        private ContactGroupRepository $contactGroupRepository,
        private ResourceAccessRepository $resourceAccessRepository,
        private InheritedHostMacrosResolver $inheritedHostMacrosResolver,
    ) {
    }

    /**
     * With a $viewerId, the host groups, categories, severity, parent/child hosts and notification
     * contacts are scoped to what that viewer may access, as the legacy host form does: an
     * out-of-scope one gets no name, so the transformer leaves it out (no existence leak).
     *
     * @return ExtraDataTypeAlias
     */
    public function fetch(Host $host, ?UserId $viewerId = null): array
    {
        $groupIds = $this->scopeToAccessible(
            $host->hostGroupIds,
            $viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleHostGroupIds($viewerId) : null,
            HostGroupId::class,
        );
        $categoryIds = $this->scopeToAccessible(
            $host->categoryIds,
            $viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleHostCategoryIds($viewerId) : null,
            HostCategoryId::class,
        );
        $relatedHostIds = new Collection([...$host->parentHostIds->toArray(), ...$host->childHostIds->toArray()], HostId::class);
        $relatedHostIds = $this->scopeToAccessible(
            $relatedHostIds,
            // Only the related hosts are looked up, not the viewer's whole scope.
            $viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleHostIds($viewerId, $relatedHostIds) : null,
            HostId::class,
        );

        $severityId = $host->severityId;
        if ($severityId instanceof HostSeverityId && $viewerId instanceof UserId) {
            $severityIds = $this->scopeToAccessible(
                new Collection([$severityId], HostSeverityId::class),
                $this->resourceAccessRepository->findAccessibleHostSeverityIds($viewerId),
                HostSeverityId::class,
            );
            $severityId = $severityIds->toArray()[0] ?? null;
        }

        $commands = [];
        foreach ([$host->dataProcessing->eventHandlerCommandId, $host->checkOptions->checkCommandId] as $commandId) {
            if ($commandId instanceof CommandId) {
                // The command exists: the handler already validated it.
                $commands[$commandId->value] = $this->commandRepository->getById($commandId);
            }
        }

        $timePeriodIds = array_filter(
            [$host->schedulingOptions->checkTimeperiodId, $host->notifications?->periodId],
            static fn (?TimePeriodId $timePeriodId): bool => $timePeriodId instanceof TimePeriodId,
        );

        $iconId = $host->extendedInformations?->iconId;

        $timezoneName = $host->timezoneId instanceof TimezoneId ? $this->timezoneRepository->findNameById($host->timezoneId) : null;
        $severityName = $severityId instanceof HostSeverityId ? $this->hostSeverityRepository->findNameById($severityId) : null;

        // Every macro the host effectively has: its own (with their stored ids), then those it still
        // inherits, each with the id + parent a client resends to change it.
        $macros = $this->inheritedHostMacrosResolver
            ->resolve($host->templateIds, $host->checkOptions->checkCommandId)
            ->effectiveWith($host->checkOptions->macros);

        $contactNames = [];
        $contactGroupNames = [];
        if ($host->notifications instanceof Notifications) {
            $contactIds = $this->scopeToAccessible(
                $host->notifications->contactIds,
                $viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleContactIds($viewerId) : null,
                NotificationContactId::class,
            );
            $contactGroupIds = $this->scopeToAccessible(
                $host->notifications->contactGroupIds,
                $viewerId instanceof UserId ? $this->resourceAccessRepository->findAccessibleContactGroupIds($viewerId) : null,
                ContactGroupId::class,
            );
            $contactNames = $this->notificationContactRepository->findNamesByIds($contactIds)->toArray();
            $contactGroupNames = $this->contactGroupRepository->findNamesByIds($contactGroupIds)->toArray();
        }

        /** @var ExtraDataTypeAlias $extraData indexed by id, as the repositories return them */
        $extraData = [
            'pollerNames' => $this->pollerRepository->findNamesByIds(new Collection([$host->pollerId], PollerId::class))->toArray(),
            'groupNames' => $this->hostGroupRepository->findNamesByIds($groupIds)->toArray(),
            'templateNames' => $this->hostTemplateRepository->findNamesByIds($host->templateIds)->toArray(),
            'categoryNames' => $this->hostCategoryRepository->findNamesByIds($categoryIds)->toArray(),
            'hostNames' => $this->hostRepository->findNamesByIds($relatedHostIds)->toArray(),
            'timezoneNames' => $timezoneName instanceof TimezoneName ? [$host->timezoneId?->value => $timezoneName] : [],
            'severityNames' => $severityName instanceof HostSeverityName ? [$severityId?->value => $severityName] : [],
            'commands' => $commands,
            'timePeriodNames' => $this->timePeriodRepository->findNamesByIds(new Collection(array_values($timePeriodIds), TimePeriodId::class))->toArray(),
            'icons' => $iconId instanceof MediaId ? $this->mediaRepository->findByIds(new Collection([$iconId], MediaId::class))->toArray() : [],
            'macros' => $macros,
            'contactNames' => $contactNames,
            'contactGroupNames' => $contactGroupNames,
        ];

        return $extraData;
    }

    /**
     * Keeps only the ids the viewer may access. A null $accessible means no restriction applies
     * (no viewer, or an unrestricted dimension), so the ids pass through unchanged.
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
}
