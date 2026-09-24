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
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreateHostInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\DataProcessingInput;
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
        );

        $host = $this->commandBus->execute($command);
        Assert::isInstanceOf($host, Host::class);

        return $this->transformer->transform($host);
    }

    private function triStateOrDefault(?TriStateEnum $value): TriStateEnum
    {
        return $value ?? TriStateEnum::UseDefault;
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
