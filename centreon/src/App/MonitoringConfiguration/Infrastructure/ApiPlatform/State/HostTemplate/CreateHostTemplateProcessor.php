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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\MonitoringConfiguration\Application\Command\CreateHostTemplateCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\SchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\EnumResolver\NotificationOptionEnumResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\CreateHostTemplateCheckOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\CreateHostTemplateDataProcessingInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\CreateHostTemplateExtendedInformationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\CreateHostTemplateInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\CreateHostTemplateNotificationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateMacroInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate\HostTemplateResource;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * Answers with the detail of the created template, as read back.
 *
 * @implements ProcessorInterface<CreateHostTemplateInput, HostTemplateResource>
 */
final readonly class CreateHostTemplateProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private Security $security,
        private HostTemplateRepository $repository,
        private HostTemplateResourceBuilder $resourceBuilder,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): HostTemplateResource
    {
        $credentialUser = $this->security->getUser();
        Assert::isInstanceOf($credentialUser, CredentialUser::class);
        Assert::string($data->name);
        Assert::string($data->alias);
        $actor = $credentialUser->credential->actingUser();

        $checkOptions = $data->checkOptions ?? new CreateHostTemplateCheckOptionsInput();

        $command = new CreateHostTemplateCommand(
            name: HostTemplateName::fromUserInput($data->name),
            alias: new HostAlias($data->alias),
            actor: $actor,
            snmpVersion: $data->snmpVersion,
            snmpCommunity: $this->trimmedOrNull($data->snmpCommunity),
            timezoneId: $data->timezoneId !== null ? new TimezoneId($data->timezoneId) : null,
            severityId: $data->severityId !== null ? new HostSeverityId($data->severityId) : null,
            templateIds: $this->toIdCollection($data->templateIds, HostTemplateId::class),
            categoryIds: $this->toIdCollection($data->categoryIds, HostCategoryId::class),
            linkedServiceTemplateIds: $this->toIdCollection($data->linkedServiceTemplateIds, ServiceTemplateId::class),
            checkCommandId: $checkOptions->commandId !== null ? new CommandId($checkOptions->commandId) : null,
            checkCommandArgs: array_values($checkOptions->args),
            macroChanges: new Collection(
                array_values(array_map($this->toMacroChange(...), $checkOptions->macros)),
                HostMacroChange::class,
            ),
            extendedInformations: $this->buildExtendedInformations($data->extendedInformations),
            schedulingOptions: new SchedulingOptions(
                checkTimeperiodId: $data->schedulingOptions?->checkTimeperiodId !== null
                    ? new TimePeriodId($data->schedulingOptions->checkTimeperiodId)
                    : null,
                maxCheckAttempts: $data->schedulingOptions?->maxCheckAttempts,
                normalCheckInterval: $data->schedulingOptions?->normalCheckInterval,
                retryCheckInterval: $data->schedulingOptions?->retryCheckInterval,
                activeCheckEnabled: $data->schedulingOptions->activeCheckEnabled ?? TriStateEnum::UseDefault,
                passiveCheckEnabled: $data->schedulingOptions->passiveCheckEnabled ?? TriStateEnum::UseDefault,
            ),
            dataProcessing: $this->buildDataProcessing($data->dataProcessing ?? new CreateHostTemplateDataProcessingInput()),
            // Built even when absent, on premise: the columns are written either way.
            notifications: $this->isCloudPlatform ? null : $this->buildNotifications($data->notifications ?? new CreateHostTemplateNotificationsInput()),
        );

        $template = $this->commandBus->execute($command);
        Assert::isInstanceOf($template, HostTemplate::class);

        $created = $this->repository->findOne($template->id());
        Assert::isInstanceOf($created, HostTemplate::class);

        return $this->resourceBuilder->build($created, $actor);
    }

    private function toMacroChange(HostTemplateMacroInput $input): HostMacroChange
    {
        return new HostMacroChange(
            name: new HostMacroName($input->name),
            value: $input->value,
            isPassword: $input->isPassword,
            id: $input->id !== null ? new HostMacroId($input->id) : null,
            parent: $input->parent !== null ? HostMacroParentEnum::from($input->parent) : null,
        );
    }

    private function buildExtendedInformations(?CreateHostTemplateExtendedInformationsInput $input): ?ExtendedInformations
    {
        if (! $input instanceof CreateHostTemplateExtendedInformationsInput) {
            return null;
        }

        return new ExtendedInformations(
            noteUrl: $this->trimmedOrNull($input->noteUrl),
            note: $this->trimmedOrNull($input->note),
            actionUrl: $this->trimmedOrNull($input->actionUrl),
            iconId: $input->iconId !== null ? new MediaId($input->iconId) : null,
            altIcon: $this->trimmedOrNull($input->altIcon),
            comment: $this->trimmedOrNull($input->comment),
        );
    }

    private function buildDataProcessing(CreateHostTemplateDataProcessingInput $input): DataProcessing
    {
        return new DataProcessing(
            checkFreshness: $input->checkFreshness ?? TriStateEnum::UseDefault,
            flapDetectionEnabled: $input->flapDetectionEnabled ?? TriStateEnum::UseDefault,
            eventHandlerEnabled: $input->eventHandlerEnabled ?? TriStateEnum::UseDefault,
            acknowledgmentTimeout: $input->acknowledgmentTimeout,
            freshnessThreshold: $input->freshnessThreshold,
            lowFlapThreshold: $input->lowFlapThreshold,
            highFlapThreshold: $input->highFlapThreshold,
            eventHandlerCommandId: $input->eventHandlerCommandId !== null ? new CommandId($input->eventHandlerCommandId) : null,
            eventHandlerArgs: array_values($input->eventHandlerArgs),
        );
    }

    private function buildNotifications(CreateHostTemplateNotificationsInput $input): Notifications
    {
        return new Notifications(
            enabled: TriStateEnum::from($input->enabled),
            contactIds: $this->toIdCollection($input->contacts, NotificationContactId::class),
            contactGroupIds: $this->toIdCollection($input->contactGroups, ContactGroupId::class),
            options: array_values(array_unique(
                array_map(NotificationOptionEnumResolver::toDomain(...), $input->options),
                SORT_REGULAR,
            )),
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
        $trimmed = trim($value ?? '');

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * A repeated id is kept once, at its first position.
     *
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
}
