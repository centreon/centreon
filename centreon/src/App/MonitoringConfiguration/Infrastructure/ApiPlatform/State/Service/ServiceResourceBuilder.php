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

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodName;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\GraphTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\MonitoringConfiguration\Domain\Repository\TrapRepository;
use App\MonitoringConfiguration\Domain\Service\InheritedServiceMacrosResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\ServiceNotificationOptionEnumResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\ServiceStalkingOptionEnumResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ContactGroup\ContactGroupResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\NotificationContact\NotificationContactResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceAdvancedOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceCategoryOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceCheckCommandOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceCheckOptionsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceEventHandlerCommandOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceGraphTemplateOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceGroupOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceHostOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceIconOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceMonitoringEngineOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceNotificationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceSeverityOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceTemplateOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceTrapOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\TimePeriod\TimePeriodResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use App\Security\Domain\Aggregate\ActingUser;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves the aggregate's ids into `{id, name}` outputs: own values and effective macros, ACL-scoped relations
 * filtered, on-premise-only fields dropped on Cloud. An unresolved reference is left out, except the host.
 */
final readonly class ServiceResourceBuilder
{
    /**
     * @param TransformerInterface<Service, ServiceResource> $transformer
     */
    public function __construct(
        #[Autowire(service: ServiceResourceTransformer::class)]
        private TransformerInterface $transformer,
        private HostRepository $hostRepository,
        private ServiceTemplateRepository $serviceTemplateRepository,
        private CommandRepository $commandRepository,
        private TimePeriodRepository $timePeriodRepository,
        private NotificationContactRepository $contactRepository,
        private ContactGroupRepository $contactGroupRepository,
        private MediaRepository $mediaRepository,
        private MediaUrlGenerator $mediaUrlGenerator,
        private ServiceGroupRepository $serviceGroupRepository,
        private ServiceCategoryRepository $serviceCategoryRepository,
        private ServiceSeverityRepository $serviceSeverityRepository,
        private GraphTemplateRepository $graphTemplateRepository,
        private TrapRepository $trapRepository,
        private AccessScopeResolver $accessScopes,
        private ServiceMacroTransformer $macroTransformer,
        private InheritedServiceMacrosResolver $inheritedServiceMacrosResolver,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    public function build(Service $service, ActingUser $actor): ServiceResource
    {
        $resource = $this->transformer->transform($service);

        $hostName = $this->hostRepository->findNamesByIds(new Collection([$service->hostId], HostId::class))->toArray()[$service->hostId->value] ?? null;
        $resource->host = new ServiceHostOutput($service->hostId->value, $hostName->value ?? '');

        $resource->template = $this->buildTemplate($service->templateId);
        $resource->checkOptions = $this->buildCheckOptions($service);
        $resource->monitoringEngine = $this->buildMonitoringEngine($service);
        $resource->notifications = $this->isCloudPlatform
            ? null
            : $this->buildNotifications($this->scopeNotifications($service->notifications, $actor));
        $resource->advanced = $this->buildAdvanced($service->dataProcessing, $service->extendedInformations);

        $groupIds = $this->accessScopes->serviceGroups($actor)->filter($service->serviceGroupIds);
        $groupNames = $this->serviceGroupRepository->findNamesByIds($groupIds)->toArray();
        $resource->serviceGroups = [];
        foreach ($groupIds as $groupId) {
            if (isset($groupNames[$groupId->value])) {
                $resource->serviceGroups[] = new ServiceGroupOutput($groupId->value, $groupNames[$groupId->value]->value);
            }
        }

        $categoryIds = $this->accessScopes->serviceCategories($actor)->filter($service->categoryIds);
        $categoryNames = $this->serviceCategoryRepository->findNamesByIds($categoryIds)->toArray();
        $resource->categories = [];
        foreach ($categoryIds as $categoryId) {
            if (isset($categoryNames[$categoryId->value])) {
                $resource->categories[] = new ServiceCategoryOutput($categoryId->value, $categoryNames[$categoryId->value]->value);
            }
        }

        // Hidden when out of the actor's scope.
        $resource->severity = null;
        if ($service->severityId instanceof ServiceSeverityId && $this->accessScopes->serviceSeverities($actor)->allows($service->severityId)) {
            $severityName = $this->serviceSeverityRepository->findNameById($service->severityId);
            $resource->severity = $severityName instanceof ServiceSeverityName
                ? new ServiceSeverityOutput($service->severityId->value, $severityName->value)
                : null;
        }

        $resource->graphTemplate = $this->isCloudPlatform ? null : $this->buildGraphTemplate($service->graphTemplateId);
        $resource->traps = $this->isCloudPlatform ? null : $this->buildTraps($service);

        return $resource;
    }

    private function buildTemplate(?ServiceTemplateId $templateId): ?ServiceTemplateOutput
    {
        if (! $templateId instanceof ServiceTemplateId) {
            return null;
        }

        $name = $this->serviceTemplateRepository
            ->findNamesByIds(new Collection([$templateId], ServiceTemplateId::class))
            ->toArray()[$templateId->value] ?? null;

        return $name !== null ? new ServiceTemplateOutput($templateId->value, $name->value) : null;
    }

    private function buildCheckOptions(Service $service): ServiceCheckOptionsOutput
    {
        $command = null;
        if ($service->checkOptions->checkCommandId instanceof CommandId) {
            $checkCommand = $this->commandRepository->getById($service->checkOptions->checkCommandId);
            $command = new ServiceCheckCommandOutput(
                $checkCommand->id()->value,
                $checkCommand->name->value,
                $checkCommand->isCentreonMonitoringAgent(),
            );
        }

        $macros = array_map(
            $this->macroTransformer->transform(...),
            $this->inheritedServiceMacrosResolver
                ->resolve($service->templateId, $service->checkOptions->checkCommandId)
                ->effectiveWith($service->checkOptions->macros),
        );

        return new ServiceCheckOptionsOutput($command, $service->checkOptions->args, $macros);
    }

    private function buildMonitoringEngine(Service $service): ServiceMonitoringEngineOutput
    {
        $scheduling = $service->schedulingOptions;
        $extended = $service->extendedInformations;

        return new ServiceMonitoringEngineOutput(
            checkPeriod: $this->resolveTimePeriod($scheduling->checkTimeperiodId),
            maxCheckAttempts: $scheduling->maxCheckAttempts,
            normalCheckInterval: $scheduling->normalCheckInterval,
            retryCheckInterval: $scheduling->retryCheckInterval,
            activeCheckEnabled: $this->isCloudPlatform ? null : $scheduling->activeCheckEnabled,
            passiveCheckEnabled: $this->isCloudPlatform ? null : $scheduling->passiveCheckEnabled,
            isVolatile: $this->isCloudPlatform ? null : $scheduling->isVolatile,
            noteUrl: $extended?->noteUrl,
            note: $extended?->note,
            actionUrl: $extended?->actionUrl,
            icon: $this->resolveIcon($extended?->iconId),
            altIcon: $extended?->altIcon,
            geoCoordinates: $extended?->geoCoordinates instanceof GeoCoordinates ? (string) $extended->geoCoordinates : null,
        );
    }

    private function buildAdvanced(ServiceDataProcessing $dataProcessing, ?ServiceExtendedInformations $extended): ServiceAdvancedOutput
    {
        $eventHandler = null;
        if ($dataProcessing->eventHandlerCommandId instanceof CommandId) {
            $command = $this->commandRepository->getById($dataProcessing->eventHandlerCommandId);
            $eventHandler = new ServiceEventHandlerCommandOutput($command->id()->value, $command->name->value);
        }

        return new ServiceAdvancedOutput(
            obsessOverService: $this->isCloudPlatform ? null : $dataProcessing->obsessOverService,
            acknowledgmentTimeout: $this->isCloudPlatform ? null : $dataProcessing->acknowledgmentTimeout,
            checkFreshness: $dataProcessing->checkFreshness,
            freshnessThreshold: $dataProcessing->freshnessThreshold,
            flapDetectionEnabled: $this->isCloudPlatform ? null : $dataProcessing->flapDetectionEnabled,
            lowFlapThreshold: $this->isCloudPlatform ? null : $dataProcessing->lowFlapThreshold,
            highFlapThreshold: $this->isCloudPlatform ? null : $dataProcessing->highFlapThreshold,
            retainStatusInformation: $this->isCloudPlatform ? null : $dataProcessing->retainStatusInformation,
            retainNonStatusInformation: $this->isCloudPlatform ? null : $dataProcessing->retainNonStatusInformation,
            stalkingOptions: $this->isCloudPlatform ? null : array_map(ServiceStalkingOptionEnumResolver::toString(...), $dataProcessing->stalkingOptions),
            eventHandlerEnabled: $dataProcessing->eventHandlerEnabled,
            eventHandler: $eventHandler,
            eventHandlerArgs: $this->isCloudPlatform ? [] : $dataProcessing->eventHandlerArgs,
            comment: $extended?->comment,
        );
    }

    private function buildNotifications(?ServiceNotifications $notifications): ?ServiceNotificationsOutput
    {
        if (! $notifications instanceof ServiceNotifications) {
            return null;
        }

        $contactNames = $this->contactRepository->findNamesByIds($notifications->contactIds)->toArray();
        $contacts = [];
        foreach ($notifications->contactIds as $contactId) {
            if (isset($contactNames[$contactId->value])) {
                $contacts[] = new NotificationContactResource($contactId->value, $contactNames[$contactId->value]->value);
            }
        }

        $contactGroupNames = $this->contactGroupRepository->findNamesByIds($notifications->contactGroupIds)->toArray();
        $contactGroups = [];
        foreach ($notifications->contactGroupIds as $contactGroupId) {
            if (isset($contactGroupNames[$contactGroupId->value])) {
                $contactGroups[] = new ContactGroupResource($contactGroupId->value, $contactGroupNames[$contactGroupId->value]->value);
            }
        }

        return new ServiceNotificationsOutput(
            enabled: $notifications->enabled->value,
            useOnlyContactsFromHost: $notifications->useOnlyContactsFromHost,
            contactAdditiveInheritance: $notifications->contactAdditiveInheritance,
            contactGroupAdditiveInheritance: $notifications->contactGroupAdditiveInheritance,
            contacts: $contacts,
            contactGroups: $contactGroups,
            interval: $notifications->interval,
            timeperiod: $this->resolveTimePeriod($notifications->periodId),
            options: array_map(ServiceNotificationOptionEnumResolver::toString(...), $notifications->options),
            firstDelay: $notifications->firstDelay,
            recoveryDelay: $notifications->recoveryDelay,
        );
    }

    /**
     * Notification contacts and contact groups are ACL-scoped, as the legacy form hides the out-of-scope ones.
     */
    private function scopeNotifications(?ServiceNotifications $notifications, ActingUser $actor): ?ServiceNotifications
    {
        if (! $notifications instanceof ServiceNotifications) {
            return null;
        }

        return $notifications->withContacts(
            $this->accessScopes->contacts($actor)->filter($notifications->contactIds),
            $this->accessScopes->contactGroups($actor)->filter($notifications->contactGroupIds),
        );
    }

    private function buildGraphTemplate(?GraphTemplateId $graphTemplateId): ?ServiceGraphTemplateOutput
    {
        if (! $graphTemplateId instanceof GraphTemplateId) {
            return null;
        }

        $name = $this->graphTemplateRepository
            ->findNamesByIds(new Collection([$graphTemplateId], GraphTemplateId::class))
            ->toArray()[$graphTemplateId->value] ?? null;

        return $name !== null ? new ServiceGraphTemplateOutput($graphTemplateId->value, $name->value) : null;
    }

    /**
     * @return list<ServiceTrapOutput>
     */
    private function buildTraps(Service $service): array
    {
        $traps = $this->trapRepository->findByIds($service->trapIds)->toArray();

        $outputs = [];
        foreach ($service->trapIds as $trapId) {
            $trap = $traps[$trapId->value] ?? null;
            if ($trap !== null) {
                $outputs[] = new ServiceTrapOutput(
                    $trapId->value,
                    $trap->name->value,
                    ['id' => $trap->vendor->id->value, 'name' => $trap->vendor->name->value],
                );
            }
        }

        return $outputs;
    }

    private function resolveTimePeriod(?TimePeriodId $timePeriodId): ?TimePeriodResource
    {
        if (! $timePeriodId instanceof TimePeriodId) {
            return null;
        }

        $name = $this->timePeriodRepository
            ->findNamesByIds(new Collection([$timePeriodId], TimePeriodId::class))
            ->toArray()[$timePeriodId->value] ?? null;

        return $name instanceof TimePeriodName ? new TimePeriodResource($timePeriodId->value, $name->value) : null;
    }

    private function resolveIcon(?MediaId $iconId): ?ServiceIconOutput
    {
        if (! $iconId instanceof MediaId) {
            return null;
        }

        $icon = $this->mediaRepository->findByIds(new Collection([$iconId], MediaId::class))->toArray()[$iconId->value] ?? null;

        return $icon instanceof Media
            ? new ServiceIconOutput($icon->id()->value, $icon->name->value, $this->mediaUrlGenerator->generate($icon))
            : null;
    }
}
