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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service;

use ApiPlatform\Metadata\Put;
use App\MonitoringConfiguration\Application\Command\UpdateServiceCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;
use App\MonitoringConfiguration\Domain\Service\InheritedServiceMacrosResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceAdvancedInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceCheckOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceMacroInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceMacroOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceMonitoringEngineInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceNotificationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\UpdateServiceInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\PutServiceProcessor;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceMacroTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceResourceBuilder;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceResourceTransformer;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\Role;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeContactGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeGraphTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeMediaRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeNotificationContactRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceCategoryRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceGroupRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceSeverityRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimePeriodRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTrapRepository;
use Tests\App\Shared\Double\CommandBusSpy;

/**
 * What the processor sends to the update handler: the service of the URI, the payload mapped to the domain, the
 * name collapsed as it will be stored, the repeated ids merged, the defaults of what is absent (a full replace).
 * The HTTP wiring is pinned by {@see PutServiceProcessorTest}, the rules of the payload by the input validation
 * tests.
 */
final class PutServiceProcessorLogicTest extends TestCase
{
    private const HOST_ID = 10;
    private const SERVICE_ID = 42;

    private CommandBusSpy $commandBus;

    private FakeServiceRepository $serviceRepository;

    protected function setUp(): void
    {
        $this->serviceRepository = new FakeServiceRepository();
        $this->commandBus = new CommandBusSpy();
        $this->commandBus->result = new Service(
            id: new ServiceId(self::SERVICE_ID),
            name: new ServiceName('ping'),
            hostId: new HostId(self::HOST_ID),
            hostActivated: true,
            hostPollerId: new PollerId(1),
        );
    }

    public function testItMapsThePayloadToTheUpdateCommand(): void
    {
        $this->process(new UpdateServiceInput(
            name: '  web   server  check ',
            hostId: self::HOST_ID,
            activated: false,
            serviceTemplateId: 30,
            serviceGroupIds: [80, 81, 80],
            categoryIds: [90, 90],
            severityId: 70,
            trapIds: [100, 100],
            graphTemplateId: 60,
            checkOptions: new ServiceCheckOptionsInput(commandId: 5, args: ['80', "two\nlines"]),
            monitoringEngine: new ServiceMonitoringEngineInput(
                checkTimeperiodId: 40,
                maxCheckAttempts: 3,
                normalCheckInterval: 5,
                retryCheckInterval: 1,
                activeCheckEnabled: TriStateEnum::False,
                passiveCheckEnabled: TriStateEnum::True,
                isVolatile: TriStateEnum::True,
                noteUrl: 'https://example.com/notes',
                note: 'a note',
                actionUrl: 'https://example.com/action',
                iconId: 50,
                altIcon: 'alt',
                geoCoordinates: '48.85,2.35',
            ),
            notifications: new ServiceNotificationsInput(
                enabled: 'true',
                useOnlyContactsFromHost: true,
                contactAdditiveInheritance: true,
                contactGroupAdditiveInheritance: true,
                contacts: [31, 31],
                contactGroups: [41],
                interval: 30,
                timeperiodId: 40,
                options: ['critical', 'warning', 'downtime_scheduled'],
                firstDelay: 2,
                recoveryDelay: 4,
            ),
            advanced: new ServiceAdvancedInput(
                obsessOverService: TriStateEnum::False,
                acknowledgmentTimeout: 15,
                checkFreshness: TriStateEnum::True,
                freshnessThreshold: 60,
                flapDetectionEnabled: TriStateEnum::False,
                lowFlapThreshold: 10,
                highFlapThreshold: 90,
                retainStatusInformation: TriStateEnum::True,
                retainNonStatusInformation: TriStateEnum::False,
                stalkingOptions: ['critical', 'ok'],
                eventHandlerEnabled: TriStateEnum::True,
                eventHandlerCommandId: 6,
                eventHandlerArgs: ['arg'],
                comment: 'a comment',
            ),
        ));

        $command = $this->executedCommand();
        self::assertSame([self::SERVICE_ID, false], [$command->id->value, $command->activated]);
        self::assertSame('web server check', $command->name->value);
        self::assertSame([self::HOST_ID, 30, 5, ['80', "two\nlines"], 60, 70], [
            $command->hostId->value,
            $command->templateId?->value,
            $command->checkCommandId?->value,
            $command->checkCommandArgs,
            $command->graphTemplateId?->value,
            $command->severityId?->value,
        ]);
        self::assertSame([[80, 81], [90], [100]], [$this->idValues($command->serviceGroupIds), $this->idValues($command->categoryIds), $this->idValues($command->trapIds)]);

        $scheduling = $command->schedulingOptions;
        self::assertSame(
            [40, 3, 5, 1, TriStateEnum::False, TriStateEnum::True, TriStateEnum::True],
            [$scheduling->checkTimeperiodId?->value, $scheduling->maxCheckAttempts, $scheduling->normalCheckInterval, $scheduling->retryCheckInterval, $scheduling->activeCheckEnabled, $scheduling->passiveCheckEnabled, $scheduling->isVolatile],
        );

        $extended = $command->extendedInformations;
        self::assertSame(
            ['https://example.com/notes', 'a note', 'https://example.com/action', 50, 'alt', 'a comment', '48.85,2.35'],
            [$extended?->noteUrl, $extended?->note, $extended?->actionUrl, $extended?->iconId?->value, $extended?->altIcon, $extended?->comment, (string) $extended?->geoCoordinates],
        );

        $notifications = $command->notifications;
        self::assertInstanceOf(ServiceNotifications::class, $notifications);
        self::assertSame(
            [TriStateEnum::True, true, true, true, [31], [41], 30, 40, 2, 4],
            [
                $notifications->enabled,
                $notifications->useOnlyContactsFromHost,
                $notifications->contactAdditiveInheritance,
                $notifications->contactGroupAdditiveInheritance,
                $this->idValues($notifications->contactIds),
                $this->idValues($notifications->contactGroupIds),
                $notifications->interval,
                $notifications->periodId?->value,
                $notifications->firstDelay,
                $notifications->recoveryDelay,
            ],
        );
        self::assertSame(
            [ServiceNotificationOptionEnum::Warning, ServiceNotificationOptionEnum::Critical, ServiceNotificationOptionEnum::DowntimeScheduled],
            $notifications->options,
        );

        $dataProcessing = $command->dataProcessing;
        self::assertSame(
            [TriStateEnum::False, 15, TriStateEnum::True, 60, TriStateEnum::False, 10, 90, TriStateEnum::True, TriStateEnum::False, TriStateEnum::True, 6, ['arg']],
            [
                $dataProcessing->obsessOverService,
                $dataProcessing->acknowledgmentTimeout,
                $dataProcessing->checkFreshness,
                $dataProcessing->freshnessThreshold,
                $dataProcessing->flapDetectionEnabled,
                $dataProcessing->lowFlapThreshold,
                $dataProcessing->highFlapThreshold,
                $dataProcessing->retainStatusInformation,
                $dataProcessing->retainNonStatusInformation,
                $dataProcessing->eventHandlerEnabled,
                $dataProcessing->eventHandlerCommandId?->value,
                $dataProcessing->eventHandlerArgs,
            ],
        );
        self::assertSame([ServiceStalkingOptionEnum::Ok, ServiceStalkingOptionEnum::Critical], $dataProcessing->stalkingOptions);
        self::assertSame(1, $command->actor->userId->value);
    }

    /**
     * A full replace: without block, every field takes its default; on-premise, the notification settings are
     * the default ones.
     */
    public function testWithoutAnyOptionalBlockItSendsTheDefaults(): void
    {
        $this->process(new UpdateServiceInput(name: 'ping', hostId: self::HOST_ID, activated: true));

        $command = $this->executedCommand();
        self::assertNull($command->templateId);
        self::assertNull($command->checkCommandId);
        self::assertSame([], $command->checkCommandArgs);
        self::assertSame(TriStateEnum::UseDefault, $command->schedulingOptions->activeCheckEnabled);
        self::assertSame(TriStateEnum::UseDefault, $command->dataProcessing->obsessOverService);
        self::assertSame([], $command->dataProcessing->stalkingOptions);
        self::assertInstanceOf(ServiceNotifications::class, $command->notifications);
        self::assertSame(TriStateEnum::UseDefault, $command->notifications->enabled);
        self::assertSame([], $this->idValues($command->notifications->contactIds));
        self::assertFalse($command->notifications->useOnlyContactsFromHost);
        self::assertNull($command->extendedInformations?->note);
    }

    /**
     * A Cloud platform has no notifications block: none is sent, so the stored settings are kept.
     */
    public function testOnACloudPlatformItSendsNoNotifications(): void
    {
        $this->process(new UpdateServiceInput(name: 'ping', hostId: self::HOST_ID, activated: true, serviceTemplateId: 30), isCloudPlatform: true);

        self::assertNull($this->executedCommand()->notifications);
    }

    /**
     * Each submitted macro becomes a change, in the order sent, its name upper-cased; the id and the parent tell
     * which inherited macro it overrides.
     */
    public function testMacrosAreMapped(): void
    {
        $this->process(new UpdateServiceInput(name: 'ping', hostId: self::HOST_ID, activated: true, checkOptions: new ServiceCheckOptionsInput(
            commandId: 5,
            macros: [
                new ServiceMacroInput(name: 'port', value: '8080'),
                new ServiceMacroInput(name: 'pwd', value: null, isPassword: true, id: 11, parent: 'template'),
            ],
        )));

        self::assertSame(
            [['PORT', '8080', false, null, null], ['PWD', null, true, 11, ServiceMacroParentEnum::Template]],
            array_map(
                static fn (ServiceMacroChange $change): array => [$change->name->value, $change->value, $change->isPassword, $change->id?->value, $change->parent],
                array_values($this->executedCommand()->macroChanges->toArray()),
            ),
        );
    }

    public function testWithoutCheckOptionsNoMacroIsSent(): void
    {
        $this->process(new UpdateServiceInput(name: 'ping', hostId: self::HOST_ID, activated: true, serviceTemplateId: 30));

        self::assertSame(0, count($this->executedCommand()->macroChanges));
    }

    /**
     * A macro created by the update only gets its id on insertion: the answer reads the own macros back as stored,
     * so a client can address them on a following write.
     */
    public function testItAnswersTheOwnMacrosReadBackWithTheirIds(): void
    {
        $this->commandBus->result = new Service(
            id: new ServiceId(self::SERVICE_ID),
            name: new ServiceName('ping'),
            hostId: new HostId(self::HOST_ID),
            hostActivated: true,
            hostPollerId: new PollerId(1),
            checkOptions: new ServiceCheckOptions(null, [], [new ServiceMacro(new ServiceMacroName('port'), '8080', isPassword: false)]),
        );
        $this->serviceRepository->storedMacros[self::SERVICE_ID] = [new ServiceMacro(new ServiceMacroName('port'), '8080', isPassword: false, id: new ServiceMacroId(7))];

        $resource = $this->process(new UpdateServiceInput(name: 'ping', hostId: self::HOST_ID, activated: true));

        self::assertSame(
            [[7, 'PORT', '8080', null]],
            array_map(
                static fn (ServiceMacroOutput $macro): array => [$macro->id, $macro->name, $macro->value, $macro->parent],
                $resource->checkOptions->macros,
            ),
        );
    }

    public function testItAnswersTheFormOfTheUpdatedService(): void
    {
        $resource = $this->process(new UpdateServiceInput(name: 'ping', hostId: self::HOST_ID, activated: true));

        self::assertSame([self::SERVICE_ID, 'ping', true, self::HOST_ID, 'central'], [$resource->id, $resource->name, $resource->activated, $resource->host->id, $resource->host->name]);
    }

    private function executedCommand(): UpdateServiceCommand
    {
        self::assertCount(1, $this->commandBus->executed);
        $command = $this->commandBus->executed[0];
        self::assertInstanceOf(UpdateServiceCommand::class, $command);

        return $command;
    }

    private function process(UpdateServiceInput $input, bool $isCloudPlatform = false): ServiceResource
    {
        $credential = new Credential(new CredentialIdentifier('admin'), new UserId(1), active: true);
        $credential->assignRole(new Role('ROLE_SUPER_ADMIN'));

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(new CredentialUser($credential));

        $hosts = new FakeHostRepository();
        $hosts->seed(new Host(
            id: null,
            name: new HostName('central'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
        ), self::HOST_ID);
        $templates = new FakeServiceTemplateRepository();
        $commands = new FakeCommandRepository();
        $timePeriods = new FakeTimePeriodRepository();
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/'));

        $builder = new ServiceResourceBuilder(
            transformer: new ServiceResourceTransformer(),
            hostRepository: $hosts,
            serviceTemplateRepository: $templates,
            commandRepository: $commands,
            timePeriodRepository: $timePeriods,
            contactRepository: new FakeNotificationContactRepository(),
            contactGroupRepository: new FakeContactGroupRepository(),
            mediaRepository: new FakeMediaRepository(),
            mediaUrlGenerator: new MediaUrlGenerator('/img/media/', $requestStack),
            serviceGroupRepository: new FakeServiceGroupRepository(),
            serviceCategoryRepository: new FakeServiceCategoryRepository(),
            serviceSeverityRepository: new FakeServiceSeverityRepository(),
            graphTemplateRepository: new FakeGraphTemplateRepository(),
            trapRepository: new FakeTrapRepository(),
            accessScopes: new AccessScopeResolver($hosts->resourceAccess),
            macroTransformer: new ServiceMacroTransformer(),
            inheritedServiceMacrosResolver: new InheritedServiceMacrosResolver($templates, $commands),
            isCloudPlatform: $isCloudPlatform,
        );

        return (new PutServiceProcessor($this->commandBus, $security, $builder, new ServiceMacroTransformer(), $this->serviceRepository, $isCloudPlatform))
            ->process($input, new Put(), ['id' => self::SERVICE_ID]);
    }

    /**
     * @param Collection<covariant AggregateRootId> $ids
     *
     * @return list<int>
     */
    private function idValues(Collection $ids): array
    {
        return array_values(array_map(static fn (AggregateRootId $id): int => $id->value, $ids->toArray()));
    }
}
