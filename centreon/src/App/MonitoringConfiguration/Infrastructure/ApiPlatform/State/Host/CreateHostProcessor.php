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
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
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
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CheckOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreateHostInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreateHostNotificationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\DataProcessingInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\HostMacroInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\NotificationOptionEnumResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostResource;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @phpstan-import-type ExtraDataTypeAlias from HostResourceTransformer as HostResourceExtraDataTypeAlias
 *
 * @implements ProcessorInterface<CreateHostInput, HostResource>
 */
final readonly class CreateHostProcessor implements ProcessorInterface
{
    /**
     * @param TransformerInterface<Host, HostResource, HostResourceExtraDataTypeAlias> $transformer
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
        private HostRepository $hostRepository,
        private TimezoneRepository $timezoneRepository,
        private HostSeverityRepository $hostSeverityRepository,
        private CommandRepository $commandRepository,
        private TimePeriodRepository $timePeriodRepository,
        private MediaRepository $mediaRepository,
        private NotificationContactRepository $notificationContactRepository,
        private ContactGroupRepository $contactGroupRepository,
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
            $checkOptionsInput instanceof CheckOptionsInput ? $checkOptionsInput->args : [],
            $checkOptionsInput instanceof CheckOptionsInput ? array_map(
                static fn (HostMacroInput $macro): HostMacro => new HostMacro(
                    new HostMacroName($macro->name),
                    $macro->value,
                    $macro->isPassword,
                    $macro->description,
                ),
                $checkOptionsInput->macros,
            ) : [],
        );

        // Cloud handles notifications through a different model and the input
        // validator rejects the block there, so the host simply carries none — and the
        // response omits the key rather than advertising a feature that platform lacks.
        $notifications = $this->isCloudPlatform ? null : $this->buildNotifications($data->notifications);
        $alias = $this->trimmedOrNull($data->alias);

        $command = new CreateHostCommand(
            name: new HostName($data->name),
            address: new HostAddress($data->address),
            pollerId: new PollerId($data->pollerId),
            hostGroupIds: $hostGroupIds,
            creatorId: $credentialUser->credential->userId->value,
            viewerId: $credentialUser->credential->hasUnrestrictedResourceAccess() ? null : $credentialUser->credential->userId,
            dataProcessing: $dataProcessing,
            alias: $alias !== null ? new HostAlias($alias) : null,
            templateIds: $this->toIdCollection($data->templateIds, HostTemplateId::class),
            categoryIds: $this->toIdCollection($data->categoryIds, HostCategoryId::class),
            parentHostIds: $this->toIdCollection($data->parentHostIds, HostId::class),
            childHostIds: $this->toIdCollection($data->childHostIds, HostId::class),
            snmpVersion: $data->snmpVersion,
            snmpCommunity: $this->trimmedOrNull($data->snmpCommunity),
            timezoneId: $data->timezoneId !== null ? new TimezoneId($data->timezoneId) : null,
            severityId: $data->severityId !== null ? new HostSeverityId($data->severityId) : null,
            // Absent on Cloud, where linked services are always created.
            deployServicesFromTemplates: $data->createServicesLinkedToTemplates ?? true,
            extendedInformations: $extendedInformations,
            schedulingOptions: $schedulingOptions,
            checkOptions: $checkOptions,
            notifications: $notifications,
        );

        $host = $this->commandBus->execute($command);
        Assert::isInstanceOf($host, Host::class);

        return $this->transformer->transform($host, $this->fetchLookups($host));
    }

    /**
     * @return HostResourceExtraDataTypeAlias
     */
    private function fetchLookups(Host $host): array
    {
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
        $severityName = $host->severityId instanceof HostSeverityId ? $this->hostSeverityRepository->findNameById($host->severityId) : null;

        /** @var HostResourceExtraDataTypeAlias $lookups indexed by id, as the repositories return them */
        $lookups = [
            'pollerNames' => $this->pollerRepository->findNamesByIds(new Collection([$host->pollerId], PollerId::class))->toArray(),
            'groupNames' => $this->hostGroupRepository->findNamesByIds($host->hostGroupIds)->toArray(),
            'templateNames' => $this->hostTemplateRepository->findNamesByIds($host->templateIds)->toArray(),
            'categoryNames' => $this->hostCategoryRepository->findNamesByIds($host->categoryIds)->toArray(),
            'hostNames' => $this->hostRepository->findNamesByIds(new Collection(
                [...$host->parentHostIds->toArray(), ...$host->childHostIds->toArray()],
                HostId::class,
            ))->toArray(),
            'timezoneNames' => $timezoneName instanceof TimezoneName ? [$host->timezoneId?->value => $timezoneName] : [],
            'severityNames' => $severityName instanceof HostSeverityName ? [$host->severityId?->value => $severityName] : [],
            'commands' => $commands,
            'timePeriodNames' => $this->timePeriodRepository->findNamesByIds(new Collection(array_values($timePeriodIds), TimePeriodId::class))->toArray(),
            'icons' => $iconId instanceof MediaId ? $this->mediaRepository->findByIds(new Collection([$iconId], MediaId::class))->toArray() : [],
            'contactNames' => $host->notifications instanceof Notifications
                ? $this->notificationContactRepository->findNamesByIds($host->notifications->contactIds)->toArray()
                : [],
            'contactGroupNames' => $host->notifications instanceof Notifications
                ? $this->contactGroupRepository->findNamesByIds($host->notifications->contactGroupIds)->toArray()
                : [],
        ];

        return $lookups;
    }

    private function triStateOrDefault(?TriStateEnum $value): TriStateEnum
    {
        return $value ?? TriStateEnum::UseDefault;
    }

    /**
     * Always built on-premise, even for a request carrying no notification block: the columns are
     * written either way (with the Default tri-state), so the response reports what was actually
     * persisted rather than dropping the key.
     */
    private function buildNotifications(?CreateHostNotificationsInput $input): Notifications
    {
        $input ??= new CreateHostNotificationsInput();

        // A client repeating a contact or contact group id is tolerated: Notifications collapses it.
        return new Notifications(
            enabled: TriStateEnum::from($input->enabled),
            contactIds: new Collection(
                array_map(static fn (int $id): NotificationContactId => new NotificationContactId($id), $input->contacts),
                NotificationContactId::class,
            ),
            contactGroupIds: new Collection(
                array_map(static fn (int $id): ContactGroupId => new ContactGroupId($id), $input->contactGroups),
                ContactGroupId::class,
            ),
            options: array_map(NotificationOptionEnumResolver::toDomain(...), $input->options),
            interval: $input->interval,
            periodId: $input->timeperiodId !== null ? new TimePeriodId($input->timeperiodId) : null,
            firstDelay: $input->firstDelay,
            recoveryDelay: $input->recoveryDelay,
            contactAdditiveInheritance: $input->contactAdditiveInheritance,
            contactGroupAdditiveInheritance: $input->contactGroupAdditiveInheritance,
        );
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
}
