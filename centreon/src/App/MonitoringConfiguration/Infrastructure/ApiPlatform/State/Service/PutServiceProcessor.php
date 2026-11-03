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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\MonitoringConfiguration\Application\Command\UpdateServiceCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\ServiceNotificationOptionEnumResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\ServiceStalkingOptionEnumResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\CreateServiceInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceAdvancedInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceMonitoringEngineInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceNotificationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\UpdateServiceInput;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * Maps the update payload, a full replace, to the update command of the service named by the URI, and answers the
 * form of the updated service as a Get does (what the actor cannot see stays hidden). The mapping is the create
 * one's ({@see CreateServiceProcessor}), deliberately not shared, as for hosts.
 *
 * @implements ProcessorInterface<UpdateServiceInput, ServiceResource>
 */
final readonly class PutServiceProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private Security $security,
        private ServiceResourceBuilder $resourceBuilder,
        private ServiceMacroTransformer $macroTransformer,
        private ServiceRepository $serviceRepository,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ServiceResource
    {
        Assert::isInstanceOf($data, UpdateServiceInput::class);
        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);
        $actor = $credentialUser->credential->actingUser();
        Assert::keyExists($uriVariables, 'id');
        Assert::integer($uriVariables['id']);

        $checkOptions = $data->checkOptions;
        $command = new UpdateServiceCommand(
            id: new ServiceId($uriVariables['id']),
            // Stored as it is compared and measured by the input: trimmed, inner runs of spaces collapsed.
            name: new ServiceName(CreateServiceInput::normalizeName($data->name)),
            hostId: new HostId($data->hostId),
            activated: $data->activated,
            actor: $actor,
            templateId: $data->serviceTemplateId !== null ? new ServiceTemplateId($data->serviceTemplateId) : null,
            checkCommandId: $checkOptions?->commandId !== null ? new CommandId($checkOptions->commandId) : null,
            checkCommandArgs: array_values($checkOptions->args ?? []),
            schedulingOptions: $this->schedulingOptions($data->monitoringEngine),
            // A full replace: on-premise, the notification settings are written, the default ones without block; a
            // Cloud platform has none, and the handler keeps the stored ones.
            notifications: $this->isCloudPlatform ? null : $this->notifications($data->notifications ?? new ServiceNotificationsInput()),
            dataProcessing: $this->dataProcessing($data->advanced ?? new ServiceAdvancedInput()),
            extendedInformations: $this->extendedInformations($data->monitoringEngine, $data->advanced),
            graphTemplateId: $data->graphTemplateId !== null ? new GraphTemplateId($data->graphTemplateId) : null,
            severityId: $data->severityId !== null ? new ServiceSeverityId($data->severityId) : null,
            serviceGroupIds: $this->ids($data->serviceGroupIds, ServiceGroupId::class),
            categoryIds: $this->ids($data->categoryIds, ServiceCategoryId::class),
            trapIds: $this->ids($data->trapIds, TrapId::class),
            macroChanges: new Collection(
                array_values(array_map($this->macroTransformer->toChange(...), $checkOptions->macros ?? [])),
                ServiceMacroChange::class,
            ),
        );

        $service = $this->commandBus->execute($command);
        Assert::isInstanceOf($service, Service::class);

        // The own macros are read back as stored, since a macro created by this update only gets its id on
        // insertion.
        return $this->resourceBuilder->build(
            $service->with(checkOptions: new ServiceCheckOptions(
                $service->checkOptions->checkCommandId,
                $service->checkOptions->args,
                array_values($this->serviceRepository->findMacros($service->id())->toArray()),
            )),
            $actor,
        );
    }

    private function schedulingOptions(?ServiceMonitoringEngineInput $input): ServiceSchedulingOptions
    {
        return new ServiceSchedulingOptions(
            checkTimeperiodId: $input?->checkTimeperiodId !== null ? new TimePeriodId($input->checkTimeperiodId) : null,
            maxCheckAttempts: $input?->maxCheckAttempts,
            normalCheckInterval: $input?->normalCheckInterval,
            retryCheckInterval: $input?->retryCheckInterval,
            activeCheckEnabled: $input->activeCheckEnabled ?? TriStateEnum::UseDefault,
            passiveCheckEnabled: $input->passiveCheckEnabled ?? TriStateEnum::UseDefault,
            isVolatile: $input->isVolatile ?? TriStateEnum::UseDefault,
        );
    }

    private function notifications(ServiceNotificationsInput $input): ServiceNotifications
    {
        // A repeated contact or contact group is merged by the value object.
        return new ServiceNotifications(
            enabled: TriStateEnum::from($input->enabled),
            contactIds: $this->ids($input->contacts, NotificationContactId::class),
            contactGroupIds: $this->ids($input->contactGroups, ContactGroupId::class),
            options: array_map(ServiceNotificationOptionEnumResolver::toDomain(...), $input->options),
            interval: $input->interval,
            periodId: $input->timeperiodId !== null ? new TimePeriodId($input->timeperiodId) : null,
            firstDelay: $input->firstDelay,
            recoveryDelay: $input->recoveryDelay,
            contactAdditiveInheritance: $input->contactAdditiveInheritance,
            contactGroupAdditiveInheritance: $input->contactGroupAdditiveInheritance,
            useOnlyContactsFromHost: $input->useOnlyContactsFromHost,
        );
    }

    private function dataProcessing(ServiceAdvancedInput $input): ServiceDataProcessing
    {
        return new ServiceDataProcessing(
            checkFreshness: $input->checkFreshness ?? TriStateEnum::UseDefault,
            flapDetectionEnabled: $input->flapDetectionEnabled ?? TriStateEnum::UseDefault,
            eventHandlerEnabled: $input->eventHandlerEnabled ?? TriStateEnum::UseDefault,
            obsessOverService: $input->obsessOverService ?? TriStateEnum::UseDefault,
            retainStatusInformation: $input->retainStatusInformation ?? TriStateEnum::UseDefault,
            retainNonStatusInformation: $input->retainNonStatusInformation ?? TriStateEnum::UseDefault,
            acknowledgmentTimeout: $input->acknowledgmentTimeout,
            freshnessThreshold: $input->freshnessThreshold,
            lowFlapThreshold: $input->lowFlapThreshold,
            highFlapThreshold: $input->highFlapThreshold,
            eventHandlerCommandId: $input->eventHandlerCommandId !== null ? new CommandId($input->eventHandlerCommandId) : null,
            eventHandlerArgs: array_values($input->eventHandlerArgs),
            stalkingOptions: array_map(ServiceStalkingOptionEnumResolver::toDomain(...), $input->stalkingOptions),
        );
    }

    /**
     * The notes, URLs, icon and coordinates of the monitoring engine section, and the comment of the advanced one.
     */
    private function extendedInformations(?ServiceMonitoringEngineInput $engine, ?ServiceAdvancedInput $advanced): ServiceExtendedInformations
    {
        return new ServiceExtendedInformations(
            noteUrl: $engine?->noteUrl,
            note: $engine?->note,
            actionUrl: $engine?->actionUrl,
            iconId: $engine?->iconId !== null ? new MediaId($engine->iconId) : null,
            altIcon: $engine?->altIcon,
            comment: $advanced?->comment,
            geoCoordinates: $engine?->geoCoordinates !== null ? GeoCoordinates::fromString($engine->geoCoordinates) : null,
        );
    }

    /**
     * Repeated ids are merged, as legacy does: the relation tables carry no unique key.
     *
     * @template T of object
     *
     * @param list<int> $ids
     * @param class-string<T> $className
     *
     * @return Collection<T>
     */
    private function ids(array $ids, string $className): Collection
    {
        return new Collection(
            array_map(static fn (int $id): object => new $className($id), array_values(array_unique($ids))),
            $className,
        );
    }
}
