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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\HostTemplate;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodName;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneName;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\MonitoringConfiguration\Domain\Service\InheritedHostMacrosResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\HostTemplateNotificationOptionEnumResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\ContactGroup\ContactGroupResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateCategoryOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateCheckCommandOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateCheckOptionsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateDataProcessingOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateEventHandlerCommandOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateExtendedInformationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateIconOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateLinkedServiceTemplateOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateNotificationsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateParentOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateSchedulingOptionsOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateSeverityOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateTimezoneOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\NotificationContact\NotificationContactResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\TimePeriod\TimePeriodResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostMacroTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use App\Security\Domain\Aggregate\ActingUser;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves the ids the template carries into the `{id, name}` objects of the detail. The categories,
 * the severity, the contacts and the contact groups out of the actor's scope are left out.
 */
final readonly class HostTemplateResourceBuilder
{
    /**
     * @param TransformerInterface<HostTemplate, HostTemplateResource> $transformer
     */
    public function __construct(
        #[Autowire(service: HostTemplateResourceTransformer::class)]
        private TransformerInterface $transformer,
        private HostTemplateRepository $hostTemplateRepository,
        private HostCategoryRepository $hostCategoryRepository,
        private HostSeverityRepository $hostSeverityRepository,
        private ServiceTemplateRepository $serviceTemplateRepository,
        private CommandRepository $commandRepository,
        private TimezoneRepository $timezoneRepository,
        private TimePeriodRepository $timePeriodRepository,
        private MediaRepository $mediaRepository,
        private MediaUrlGenerator $mediaUrlGenerator,
        private NotificationContactRepository $contactRepository,
        private ContactGroupRepository $contactGroupRepository,
        private AccessScopeResolver $accessScopes,
        private HostMacroTransformer $macroTransformer,
        private InheritedHostMacrosResolver $inheritedHostMacrosResolver,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    public function build(HostTemplate $template, ActingUser $actor): HostTemplateResource
    {
        $resource = $this->transformer->transform($template);

        $parentNames = $this->hostTemplateRepository->findNamesByIds($template->parentTemplateIds)->toArray();
        foreach ($template->parentTemplateIds as $parentId) {
            if (isset($parentNames[$parentId->value])) {
                $resource->templates[] = new HostTemplateParentOutput($parentId->value, $parentNames[$parentId->value]->value);
            }
        }

        $categoryIds = $this->accessScopes->hostCategories($actor)->filter($template->categoryIds);
        $categoryNames = $this->hostCategoryRepository->findNamesByIds($categoryIds)->toArray();
        foreach ($categoryIds as $categoryId) {
            if (isset($categoryNames[$categoryId->value])) {
                $resource->categories[] = new HostTemplateCategoryOutput($categoryId->value, $categoryNames[$categoryId->value]->value);
            }
        }

        if ($template->severityId instanceof HostSeverityId && $this->accessScopes->hostSeverities($actor)->allows($template->severityId)) {
            $severityName = $this->hostSeverityRepository->findNameById($template->severityId);
            $resource->severity = $severityName instanceof HostSeverityName
                ? new HostTemplateSeverityOutput($template->severityId->value, $severityName->value)
                : null;
        }

        $linkedNames = $this->serviceTemplateRepository->findNamesByIds($template->linkedServiceTemplateIds)->toArray();
        foreach ($template->linkedServiceTemplateIds as $serviceTemplateId) {
            if (isset($linkedNames[$serviceTemplateId->value])) {
                $resource->linkedServiceTemplates[] = new HostTemplateLinkedServiceTemplateOutput(
                    $serviceTemplateId->value,
                    $linkedNames[$serviceTemplateId->value]->value,
                );
            }
        }

        if ($template->timezoneId instanceof TimezoneId) {
            $timezoneName = $this->timezoneRepository->findNameById($template->timezoneId);
            $resource->timezone = $timezoneName instanceof TimezoneName
                ? new HostTemplateTimezoneOutput($template->timezoneId->value, $timezoneName->value)
                : null;
        }

        $resource->checkOptions = $this->buildCheckOptions($template);
        $resource->extendedInformations = $this->buildExtendedInformations($template->extendedInformations);
        $resource->schedulingOptions = new HostTemplateSchedulingOptionsOutput(
            checkPeriod: $this->resolveTimePeriod($template->schedulingOptions->checkTimeperiodId),
            maxCheckAttempts: $template->schedulingOptions->maxCheckAttempts,
            normalCheckInterval: $template->schedulingOptions->normalCheckInterval,
            retryCheckInterval: $template->schedulingOptions->retryCheckInterval,
            activeCheckEnabled: $this->isCloudPlatform ? null : $template->schedulingOptions->activeCheckEnabled,
            passiveCheckEnabled: $this->isCloudPlatform ? null : $template->schedulingOptions->passiveCheckEnabled,
        );
        $resource->dataProcessing = $this->buildDataProcessing($template->dataProcessing);
        // Notifications are handled by another model on a Cloud platform.
        $resource->notifications = $this->isCloudPlatform ? null : $this->buildNotifications($template->notifications, $actor);

        return $resource;
    }

    /**
     * The inherited macros come from the active ancestors and from the check command, the template's own
     * or else the nearest ancestor's. The template's own linked service templates do not feed them.
     */
    private function buildCheckOptions(HostTemplate $template): HostTemplateCheckOptionsOutput
    {
        $command = null;
        if ($template->checkOptions->checkCommandId instanceof CommandId) {
            $checkCommand = $this->commandRepository->getById($template->checkOptions->checkCommandId);
            $command = new HostTemplateCheckCommandOutput($checkCommand->id()->value, $checkCommand->name->value);
        }

        $ownMacros = array_values(array_map(
            static fn (HostTemplateMacro $macro): HostMacro => $macro->toHostMacro(),
            $template->checkOptions->macros->toArray(),
        ));
        $inherited = $this->inheritedHostMacrosResolver->resolve($template->parentTemplateIds, $template->checkOptions->checkCommandId);

        return new HostTemplateCheckOptionsOutput(
            $command,
            $template->checkOptions->args,
            array_map($this->macroTransformer->transform(...), [...$ownMacros, ...$inherited->notOverriddenBy($ownMacros)]),
        );
    }

    private function buildExtendedInformations(?HostTemplateExtendedInformations $informations): ?HostTemplateExtendedInformationsOutput
    {
        if (! $informations instanceof HostTemplateExtendedInformations) {
            return null;
        }

        $icon = $this->resolveIcon($informations->iconId);
        if ($informations->noteUrl === null
            && $informations->note === null
            && $informations->actionUrl === null
            && ! $icon instanceof HostTemplateIconOutput
            && $informations->altIcon === null
            && $informations->comment === null
        ) {
            return null;
        }

        return new HostTemplateExtendedInformationsOutput(
            noteUrl: $informations->noteUrl,
            note: $informations->note,
            actionUrl: $informations->actionUrl,
            icon: $icon,
            altIcon: $informations->altIcon,
            comment: $informations->comment,
        );
    }

    private function buildDataProcessing(HostTemplateDataProcessing $dataProcessing): HostTemplateDataProcessingOutput
    {
        $eventHandler = null;
        if ($dataProcessing->eventHandlerCommandId instanceof CommandId) {
            $command = $this->commandRepository->getById($dataProcessing->eventHandlerCommandId);
            $eventHandler = new HostTemplateEventHandlerCommandOutput($command->id()->value, $command->name->value);
        }

        return new HostTemplateDataProcessingOutput(
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

    private function buildNotifications(?HostTemplateNotifications $notifications, ActingUser $actor): ?HostTemplateNotificationsOutput
    {
        if (! $notifications instanceof HostTemplateNotifications) {
            return null;
        }

        $contactIds = $this->accessScopes->contacts($actor)->filter($notifications->contactIds);
        $contactNames = $this->contactRepository->findNamesByIds($contactIds)->toArray();
        $contacts = [];
        foreach ($contactIds as $contactId) {
            if (isset($contactNames[$contactId->value])) {
                $contacts[] = new NotificationContactResource($contactId->value, $contactNames[$contactId->value]->value);
            }
        }

        $contactGroupIds = $this->accessScopes->contactGroups($actor)->filter($notifications->contactGroupIds);
        $contactGroupNames = $this->contactGroupRepository->findNamesByIds($contactGroupIds)->toArray();
        $contactGroups = [];
        foreach ($contactGroupIds as $contactGroupId) {
            if (isset($contactGroupNames[$contactGroupId->value])) {
                $contactGroups[] = new ContactGroupResource($contactGroupId->value, $contactGroupNames[$contactGroupId->value]->value);
            }
        }

        return new HostTemplateNotificationsOutput(
            enabled: $notifications->enabled->value,
            contacts: $contacts,
            contactGroups: $contactGroups,
            options: array_map(HostTemplateNotificationOptionEnumResolver::toString(...), $notifications->options),
            interval: $notifications->interval,
            timeperiod: $this->resolveTimePeriod($notifications->periodId),
            firstDelay: $notifications->firstDelay,
            recoveryDelay: $notifications->recoveryDelay,
            contactAdditiveInheritance: $notifications->contactAdditiveInheritance,
            contactGroupAdditiveInheritance: $notifications->contactGroupAdditiveInheritance,
        );
    }

    private function resolveTimePeriod(?TimePeriodId $periodId): ?TimePeriodResource
    {
        if (! $periodId instanceof TimePeriodId) {
            return null;
        }

        $name = $this->timePeriodRepository->findNamesByIds(new Collection([$periodId], TimePeriodId::class))->toArray()[$periodId->value] ?? null;

        return $name instanceof TimePeriodName ? new TimePeriodResource($periodId->value, $name->value) : null;
    }

    private function resolveIcon(?MediaId $iconId): ?HostTemplateIconOutput
    {
        if (! $iconId instanceof MediaId) {
            return null;
        }

        $icon = $this->mediaRepository->findByIds(new Collection([$iconId], MediaId::class))->toArray()[$iconId->value] ?? null;

        return $icon instanceof Media
            ? new HostTemplateIconOutput($icon->id()->value, $icon->name->value, $this->mediaUrlGenerator->generate($icon))
            : null;
    }
}
