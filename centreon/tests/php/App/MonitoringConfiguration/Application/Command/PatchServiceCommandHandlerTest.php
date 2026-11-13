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

namespace Tests\App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Application\Command\ListChange;
use App\MonitoringConfiguration\Application\Command\PatchServiceCommand;
use App\MonitoringConfiguration\Application\Command\ServiceCheckOptionsChanges;
use App\MonitoringConfiguration\Application\Command\ServiceDataProcessingChanges;
use App\MonitoringConfiguration\Application\Command\ServiceExtendedInformationsChanges;
use App\MonitoringConfiguration\Application\Command\ServiceNotificationsChanges;
use App\MonitoringConfiguration\Application\Command\ServiceSchedulingOptionsChanges;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostState;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Event\ServiceDisabled;
use App\MonitoringConfiguration\Domain\Event\ServiceEnabled;
use App\MonitoringConfiguration\Domain\Event\ServiceMassChanged;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\EventHandlerArgumentsRequireAHandlerException;
use App\MonitoringConfiguration\Domain\Exception\ExclusiveNotificationOptionException;
use App\MonitoringConfiguration\Domain\Exception\GraphTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\MediaNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\ServiceCheckArgumentsPartlyFilledException;
use App\MonitoringConfiguration\Domain\Exception\ServiceCheckCommandMissingException;
use App\MonitoringConfiguration\Domain\Exception\ServiceExtendedInformationsTooLongException;
use App\MonitoringConfiguration\Domain\Exception\ServiceNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceSeverityNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimePeriodNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\UnlicensedServiceTemplatesException;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Exception\AggregateConflictException;
use App\Shared\Domain\Exception\AggregateNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;

final class PatchServiceCommandHandlerTest extends PatchServiceCommandHandlerTestCase
{
    public function testAnUnknownServiceIsNotFound(): void
    {
        $this->expectException(ServiceNotFoundException::class);

        $this->patch($this->command(id: 999, activated: false));
    }

    public function testAServiceOutsideTheRestrictedActorScopeIsNotFoundAndUnchanged(): void
    {
        $this->serviceRepository->outOfRestrictedScopeIds = [self::SERVICE_ID];

        try {
            $this->patch($this->command(actorId: self::RESTRICTED_USER_ID, activated: false));
            self::fail('A service the actor cannot see must be reported as not found.');
        } catch (ServiceNotFoundException) {
            $this->assertNothingWritten();
        }
    }

    public function testNothingProvidedChangesNothing(): void
    {
        $this->patch($this->command());

        $this->assertNothingWritten();
    }

    /**
     * Every family provided with the value the service already has: nothing is written and nothing fires.
     */
    public function testValuesEqualToTheStoredOnesChangeNothing(): void
    {
        $this->patch($this->command(
            activated: true,
            name: 'ping',
            hostId: self::HOST_ID,
            templateId: null,
            severityId: null,
            graphTemplateId: null,
            serviceGroupIds: ListChange::replace([]),
            checkOptions: new ServiceCheckOptionsChanges(new CommandId(self::CHECK_COMMAND_ID), []),
            schedulingOptions: new ServiceSchedulingOptionsChanges(maxCheckAttempts: null, isVolatile: TriStateEnum::UseDefault),
            extendedInformations: new ServiceExtendedInformationsChanges(note: null),
            notifications: new ServiceNotificationsChanges(enabled: TriStateEnum::True),
            contactIds: ListChange::add([new NotificationContactId(31)]),
            dataProcessing: new ServiceDataProcessingChanges(checkFreshness: TriStateEnum::UseDefault, stalkingOptions: ListChange::remove([])),
        ));

        $this->assertNothingWritten();
    }

    public function testItEnablesWithTheNarrowWrite(): void
    {
        $this->store(activated: false);

        $this->patch($this->command(actorId: 4, activated: true));

        self::assertSame([['id' => self::SERVICE_ID, 'activated' => true]], $this->serviceRepository->activationUpdates);
        self::assertSame([], $this->serviceRepository->updatedServices);
        $events = $this->eventBus->getDispatchedEvents(ServiceEnabled::class);
        self::assertCount(1, $events);
        self::assertSame(4, $events[0]->creatorId);
        self::assertSame([], $this->eventBus->getDispatchedEvents(ServiceMassChanged::class));
    }

    public function testItDisablesWithTheNarrowWrite(): void
    {
        $this->patch($this->command(activated: false));

        self::assertSame([['id' => self::SERVICE_ID, 'activated' => false]], $this->serviceRepository->activationUpdates);
        self::assertCount(1, $this->eventBus->getDispatchedEvents(ServiceDisabled::class));
        self::assertSame([], $this->eventBus->getDispatchedEvents(ServiceMassChanged::class));
    }

    /**
     * The activation changed along with anything else is one mass change: no enable/disable event, no narrow write.
     */
    public function testAnActivationWithAnotherChangeIsOneMassChange(): void
    {
        $this->patch($this->command(activated: false, name: 'pong'));

        $written = $this->written();
        self::assertFalse($written->activated);
        self::assertSame('pong', $written->name->value);
        self::assertSame([], $this->serviceRepository->activationUpdates);
        self::assertSame([], $this->eventBus->getDispatchedEvents(ServiceDisabled::class));
        self::assertSame($written, $this->massChange()->aggregate);
    }

    public function testItChangesOnlyTheProvidedFields(): void
    {
        $this->seedEveryReference();
        $this->store(
            dataProcessing: new ServiceDataProcessing(acknowledgmentTimeout: 5, eventHandlerCommandId: new CommandId(21)),
            extendedInformations: new ServiceExtendedInformations(note: 'kept', actionUrl: 'http://kept'),
        );

        $this->patch($this->command(
            actorId: 4,
            name: '  pong ',
            templateId: 30,
            graphTemplateId: 60,
            schedulingOptions: new ServiceSchedulingOptionsChanges(maxCheckAttempts: 3, isVolatile: TriStateEnum::True),
            extendedInformations: new ServiceExtendedInformationsChanges(note: 'a note', comment: 'a comment'),
            notifications: new ServiceNotificationsChanges(interval: 0, periodId: new TimePeriodId(41)),
            dataProcessing: new ServiceDataProcessingChanges(lowFlapThreshold: 0, highFlapThreshold: 100),
        ));

        $written = $this->written();
        self::assertSame(
            ['pong', self::HOST_ID, 30, self::CHECK_COMMAND_ID, 60, 3, TriStateEnum::True, null, 'a note', 'http://kept', 'a comment', 0, 41, [31], 5, 0, 100, 21],
            [
                $written->name->value,
                $written->hostId->value,
                $written->templateId?->value,
                $written->checkOptions->checkCommandId?->value,
                $written->graphTemplateId?->value,
                $written->schedulingOptions->maxCheckAttempts,
                $written->schedulingOptions->isVolatile,
                $written->schedulingOptions->normalCheckInterval,
                $written->extendedInformations?->note,
                $written->extendedInformations?->actionUrl,
                $written->extendedInformations?->comment,
                $written->notifications?->interval,
                $written->notifications?->periodId?->value,
                $written->notifications instanceof ServiceNotifications ? $this->idValues($written->notifications->contactIds) : null,
                $written->dataProcessing->acknowledgmentTimeout,
                $written->dataProcessing->lowFlapThreshold,
                $written->dataProcessing->highFlapThreshold,
                $written->dataProcessing->eventHandlerCommandId?->value,
            ],
        );
        $event = $this->massChange();
        self::assertSame(4, $event->creatorId);
        self::assertNull($event->previousHostId);
        self::assertNull($event->previousPollerId);
    }

    /**
     * Null clears a value of each family: a text, a reference, a number, a tri-state and a list.
     */
    public function testNullClearsAValueOfEachFamily(): void
    {
        $this->addTemplate(30, checkCommandId: self::CHECK_COMMAND_ID);
        $this->store(
            templateId: 30,
            args: ['80'],
            dataProcessing: new ServiceDataProcessing(obsessOverService: TriStateEnum::True, freshnessThreshold: 30),
            extendedInformations: new ServiceExtendedInformations(note: 'a note', iconId: new MediaId(50)),
        );
        $this->serviceRepository->services[self::SERVICE_ID] = $this->serviceRepository->services[self::SERVICE_ID]->with(
            schedulingOptions: new ServiceSchedulingOptions(checkTimeperiodId: new TimePeriodId(40), maxCheckAttempts: 3),
            graphTemplateId: new GraphTemplateId(60),
        );

        $this->patch($this->command(
            graphTemplateId: null,
            checkOptions: new ServiceCheckOptionsChanges(args: []),
            schedulingOptions: new ServiceSchedulingOptionsChanges(checkTimeperiodId: null, maxCheckAttempts: null),
            extendedInformations: new ServiceExtendedInformationsChanges(note: null, iconId: null),
            dataProcessing: new ServiceDataProcessingChanges(obsessOverService: TriStateEnum::UseDefault, freshnessThreshold: null),
        ));

        $written = $this->written();
        self::assertSame(
            [null, [], null, null, null, null, TriStateEnum::UseDefault, null, 30, self::CHECK_COMMAND_ID],
            [
                $written->graphTemplateId,
                $written->checkOptions->args,
                $written->schedulingOptions->checkTimeperiodId,
                $written->schedulingOptions->maxCheckAttempts,
                $written->extendedInformations?->note,
                $written->extendedInformations?->iconId,
                $written->dataProcessing->obsessOverService,
                $written->dataProcessing->freshnessThreshold,
                $written->templateId?->value,
                $written->checkOptions->checkCommandId?->value,
            ],
        );
    }

    /**
     * A move to another host carries the previous host, and the poller of that host when it was enabled and is not
     * the new one; the service is built with the state of its new host.
     */
    public function testAMoveCarriesThePreviousHostAndPoller(): void
    {
        $this->hostRepository->states[self::OTHER_HOST_ID] = new HostState(false, new PollerId(self::OTHER_POLLER_ID));

        $this->patch($this->command(hostId: self::OTHER_HOST_ID));

        $written = $this->written();
        self::assertSame([self::OTHER_HOST_ID, false, self::OTHER_POLLER_ID], [$written->hostId->value, $written->hostActivated, $written->hostPollerId?->value]);
        $event = $this->massChange();
        self::assertSame([self::HOST_ID, self::POLLER_ID], [$event->previousHostId?->value, $event->previousPollerId?->value]);
    }

    /**
     * @param array{bool, int} $previousHost whether the host left was enabled, and its poller
     */
    #[DataProvider('moveWithoutPreviousPollerProvider')]
    public function testAMoveFlagsThePreviousPollerOnlyWhenItIsAnotherOneOfAnEnabledHost(array $previousHost, int $newPollerId): void
    {
        [$previousHostActivated] = $previousHost;
        $this->store(hostActivated: $previousHostActivated);
        $this->hostRepository->states[self::OTHER_HOST_ID] = new HostState(true, new PollerId($newPollerId));

        $this->patch($this->command(hostId: self::OTHER_HOST_ID));

        $event = $this->massChange();
        self::assertSame(self::HOST_ID, $event->previousHostId?->value);
        self::assertNull($event->previousPollerId);
    }

    /**
     * @return iterable<string, array{array{bool, int}, int}>
     */
    public static function moveWithoutPreviousPollerProvider(): iterable
    {
        yield 'from a disabled host' => [[false, self::POLLER_ID], self::OTHER_POLLER_ID];

        yield 'onto the same poller' => [[true, self::POLLER_ID], self::POLLER_ID];
    }

    /**
     * Every reference provided is checked again as the authority, and nothing is written when one is refused.
     *
     * @param \Closure(self): PatchServiceCommand $command
     * @param class-string<AggregateNotFoundException|AggregateConflictException> $exceptionClass
     * @param array<string, mixed> $criteria
     */
    #[DataProvider('refusedReferenceProvider')]
    public function testItRefusesAMissingReference(\Closure $command, string $exceptionClass, array $criteria): void
    {
        $this->seedEveryReference();

        try {
            $this->patch($command($this));
            self::fail('The reference must be refused.');
        } catch (AggregateNotFoundException|AggregateConflictException $exception) {
            self::assertInstanceOf($exceptionClass, $exception);
            self::assertSame($criteria, $exception->criteria);
        }

        $this->assertNothingWritten();
    }

    /**
     * @return iterable<string, array{\Closure(self): PatchServiceCommand, class-string, array<string, mixed>}>
     */
    public static function refusedReferenceProvider(): iterable
    {
        yield 'unknown host' => [static fn (self $test): PatchServiceCommand => $test->command(hostId: 999), HostNotFoundException::class, ['hostId' => [999]]];

        yield 'unknown service template' => [static fn (self $test): PatchServiceCommand => $test->command(templateId: 999), ServiceTemplateNotFoundException::class, ['serviceTemplateId' => 999]];

        yield 'unknown severity' => [static fn (self $test): PatchServiceCommand => $test->command(severityId: 999), ServiceSeverityNotFoundException::class, ['severityId' => 999]];

        yield 'unknown graph template' => [static fn (self $test): PatchServiceCommand => $test->command(graphTemplateId: 999), GraphTemplateNotFoundException::class, ['graphTemplateId' => 999]];

        yield 'unknown check time period' => [
            static fn (self $test): PatchServiceCommand => $test->command(schedulingOptions: new ServiceSchedulingOptionsChanges(checkTimeperiodId: new TimePeriodId(999))),
            TimePeriodNotFoundException::class,
            ['id' => 999],
        ];

        yield 'unknown notification time period' => [
            static fn (self $test): PatchServiceCommand => $test->command(notifications: new ServiceNotificationsChanges(periodId: new TimePeriodId(999))),
            TimePeriodNotFoundException::class,
            ['id' => 999],
        ];

        yield 'unknown icon' => [
            static fn (self $test): PatchServiceCommand => $test->command(extendedInformations: new ServiceExtendedInformationsChanges(iconId: new MediaId(999))),
            MediaNotFoundException::class,
            ['id' => 999],
        ];

        yield 'unknown check command' => [
            static fn (self $test): PatchServiceCommand => $test->command(checkOptions: new ServiceCheckOptionsChanges(new CommandId(999))),
            CommandNotFoundException::class,
            ['id' => 999],
        ];

        yield 'check command of another type' => [
            static fn (self $test): PatchServiceCommand => $test->command(checkOptions: new ServiceCheckOptionsChanges(new CommandId(22))),
            CommandNotFoundException::class,
            ['id' => 22],
        ];

        yield 'unknown event handler' => [
            static fn (self $test): PatchServiceCommand => $test->command(dataProcessing: new ServiceDataProcessingChanges(eventHandlerCommandId: new CommandId(999))),
            CommandNotFoundException::class,
            ['id' => 999],
        ];
    }

    /**
     * For an actor whose access is restricted, a host or a severity outside their scope is refused as if it did not
     * exist.
     *
     * @param \Closure(self): PatchServiceCommand $command
     * @param class-string<AggregateNotFoundException> $exceptionClass
     * @param array<string, mixed> $criteria
     */
    #[DataProvider('referenceOutsideTheScopeProvider')]
    public function testItRefusesAReferenceOutsideTheActorScope(\Closure $command, string $exceptionClass, array $criteria): void
    {
        $this->seedEveryReference();
        $this->restrictTheActor();

        try {
            $this->patch($command($this));
            self::fail('The reference must be refused.');
        } catch (AggregateNotFoundException $exception) {
            self::assertInstanceOf($exceptionClass, $exception);
            self::assertSame($criteria, $exception->criteria);
        }

        $this->assertNothingWritten();
    }

    /**
     * @return iterable<string, array{\Closure(self): PatchServiceCommand, class-string, array<string, mixed>}>
     */
    public static function referenceOutsideTheScopeProvider(): iterable
    {
        yield 'host' => [
            static fn (self $test): PatchServiceCommand => $test->command(actorId: self::RESTRICTED_USER_ID, hostId: self::OTHER_HOST_ID),
            HostNotFoundException::class,
            ['hostId' => [self::OTHER_HOST_ID]],
        ];

        yield 'severity' => [
            static fn (self $test): PatchServiceCommand => $test->command(actorId: self::RESTRICTED_USER_ID, severityId: 71),
            ServiceSeverityNotFoundException::class,
            ['severityId' => 71],
        ];
    }

    public function testItRefusesANewTemplateTheLicenseNoLongerAllows(): void
    {
        $this->addTemplate(30, checkCommandId: self::CHECK_COMMAND_ID);
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [30];

        try {
            $this->patch($this->command(templateId: 30));
            self::fail('A template the license no longer allows must be rejected.');
        } catch (UnlicensedServiceTemplatesException $exception) {
            self::assertSame(['serviceTemplateId' => 30], $exception->criteria);
        }

        $this->assertNothingWritten();
    }

    public function testItKeepsATemplateTheLicenseNoLongerAllows(): void
    {
        $this->addTemplate(30, checkCommandId: self::CHECK_COMMAND_ID);
        $this->store(templateId: 30);
        $this->serviceTemplateRepository->monitoringConnectorTemplateIds = [30];

        $this->patch($this->command(name: 'pong', templateId: 30));

        self::assertSame(30, $this->written()->templateId?->value);
    }

    public function testANameUsedOnTheHostIsAConflict(): void
    {
        $this->serviceRepository->services[6] = $this->serviceRepository->services[self::SERVICE_ID]->with(name: new ServiceName('PONG'));

        $this->expectException(ServiceAlreadyExistsException::class);

        $this->patch($this->command(name: 'pong'));
    }

    public function testAMoveOntoAHostWhereTheNameIsUsedIsAConflict(): void
    {
        $this->hostRepository->states[self::OTHER_HOST_ID] = new HostState(true, new PollerId(self::OTHER_POLLER_ID));
        $this->serviceRepository->services[6] = $this->serviceRepository->services[self::SERVICE_ID]->with(hostId: new HostId(self::OTHER_HOST_ID));

        try {
            $this->patch($this->command(hostId: self::OTHER_HOST_ID));
            self::fail('The name is used on the target host.');
        } catch (ServiceAlreadyExistsException $exception) {
            self::assertSame(['name' => 'ping'], $exception->criteria);
        }

        $this->assertNothingWritten();
    }

    /**
     * The name and the host left as they are need no check: a duplicate legacy stored does not block the update.
     */
    public function testKeepingTheNameAndTheHostIsNoConflict(): void
    {
        $this->serviceRepository->forceNameUsed = true;

        $this->patch($this->command(name: 'ping', hostId: self::HOST_ID, schedulingOptions: new ServiceSchedulingOptionsChanges(maxCheckAttempts: 2)));

        self::assertSame('ping', $this->written()->name->value);
    }

    /**
     * The references are checked before the name: a restricted actor cannot learn that a name exists on a host they
     * cannot use.
     */
    public function testAReferenceIsRefusedBeforeTheName(): void
    {
        $this->seedEveryReference();
        $this->restrictTheActor();
        $this->serviceRepository->forceNameUsed = true;

        $this->expectException(HostNotFoundException::class);

        $this->patch($this->command(actorId: self::RESTRICTED_USER_ID, hostId: self::OTHER_HOST_ID));
    }

    /**
     * Removing the check command clears its arguments, unless the same update provides new ones; the template's
     * command then receives them.
     *
     * @param list<string>|null $args the arguments provided, null for none
     * @param list<string> $expected
     */
    #[DataProvider('commandRemovalProvider')]
    public function testRemovingTheCheckCommandClearsItsArgumentsUnlessNewOnesAreProvided(?array $args, array $expected): void
    {
        $this->addTemplate(30, checkCommandId: self::CHECK_COMMAND_ID);
        $this->store(templateId: 30, args: ['80']);

        $this->patch($this->command(checkOptions: $args === null ? new ServiceCheckOptionsChanges(null) : new ServiceCheckOptionsChanges(null, $args)));

        $written = $this->written();
        self::assertNull($written->checkOptions->checkCommandId);
        self::assertSame($expected, $written->checkOptions->args);
    }

    /**
     * @return iterable<string, array{list<string>|null, list<string>}>
     */
    public static function commandRemovalProvider(): iterable
    {
        yield 'no arguments provided' => [null, []];

        yield 'new arguments provided' => [['443'], ['443']];
    }

    public function testArgumentsProvidedAloneKeepTheCurrentCommand(): void
    {
        $this->store(args: ['80']);

        $this->patch($this->command(checkOptions: new ServiceCheckOptionsChanges(args: ['443'])));

        $written = $this->written();
        self::assertSame(self::CHECK_COMMAND_ID, $written->checkOptions->checkCommandId?->value);
        self::assertSame(['443'], $written->checkOptions->args);
    }

    public function testArgumentsWithoutAnOwnCommandAreAcceptedWhenTheTemplateProvidesOne(): void
    {
        $this->addTemplate(31, checkCommandId: self::CHECK_COMMAND_ID);
        $this->addTemplate(30, parentId: 31);
        $this->store(templateId: 30, checkCommandId: null);

        $this->patch($this->command(checkOptions: new ServiceCheckOptionsChanges(args: ['443'])));

        self::assertSame(['443'], $this->written()->checkOptions->args);
    }

    /**
     * A service left without any check command, its own or its template's, is refused when the update touches the
     * command, the arguments or the template.
     *
     * @param \Closure(self): PatchServiceCommand $command
     */
    #[DataProvider('noCheckCommandLeftProvider')]
    public function testAServiceLeftWithoutAnyCheckCommandIsRefused(\Closure $command, ?int $templateId): void
    {
        $this->addTemplate(30);
        $this->addTemplate(32, checkCommandId: self::CHECK_COMMAND_ID);
        $this->store(templateId: 32, checkCommandId: null);

        try {
            $this->patch($command($this));
            self::fail('A service needs a check command.');
        } catch (ServiceCheckCommandMissingException $exception) {
            self::assertSame($templateId === null ? ['checkOptions' => ['commandId']] : ['serviceTemplateId' => $templateId], $exception->criteria);
        }

        $this->assertNothingWritten();
    }

    /**
     * @return iterable<string, array{\Closure(self): PatchServiceCommand, ?int}>
     */
    public static function noCheckCommandLeftProvider(): iterable
    {
        yield 'template removed' => [static fn (self $test): PatchServiceCommand => $test->command(templateId: null), null];

        yield 'template without command' => [static fn (self $test): PatchServiceCommand => $test->command(templateId: 30), 30];

        yield 'arguments on a template without command' => [
            static fn (self $test): PatchServiceCommand => $test->command(templateId: 30, checkOptions: new ServiceCheckOptionsChanges(args: ['80'])),
            30,
        ];
    }

    public function testAStoredServiceWithoutCheckCommandAcceptsAnUnrelatedChange(): void
    {
        $this->store(checkCommandId: null);

        $this->patch($this->command(name: 'pong'));

        self::assertSame('pong', $this->written()->name->value);
    }

    public function testPartlyFilledArgumentsAreRefused(): void
    {
        $this->expectException(ServiceCheckArgumentsPartlyFilledException::class);

        $this->patch($this->command(checkOptions: new ServiceCheckOptionsChanges(args: ['80', ''])));
    }

    public function testRemovingTheEventHandlerKeepsItsArgumentsForTheTemplatesHandler(): void
    {
        $this->addCommand(21, CommandTypeEnum::Miscellaneous);
        $this->addTemplate(30, checkCommandId: self::CHECK_COMMAND_ID, eventHandlerCommandId: 21);
        $this->store(templateId: 30, dataProcessing: new ServiceDataProcessing(eventHandlerCommandId: new CommandId(21), eventHandlerArgs: ['restart']));

        $this->patch($this->command(dataProcessing: new ServiceDataProcessingChanges(eventHandlerCommandId: null)));

        $written = $this->written();
        self::assertNull($written->dataProcessing->eventHandlerCommandId);
        self::assertSame(['restart'], $written->dataProcessing->eventHandlerArgs);
    }

    /**
     * @param \Closure(self): PatchServiceCommand $command
     */
    #[DataProvider('eventHandlerArgumentsWithoutHandlerProvider')]
    public function testEventHandlerArgumentsLeftWithoutAnyHandlerAreRefused(\Closure $command): void
    {
        $this->addCommand(21, CommandTypeEnum::Miscellaneous);
        $this->addTemplate(30, checkCommandId: self::CHECK_COMMAND_ID, eventHandlerCommandId: 21);
        $this->addTemplate(33, checkCommandId: self::CHECK_COMMAND_ID);
        $this->store(dataProcessing: new ServiceDataProcessing(eventHandlerCommandId: new CommandId(21), eventHandlerArgs: ['restart']));

        $this->expectException(EventHandlerArgumentsRequireAHandlerException::class);

        $this->patch($command($this));
    }

    /**
     * @return iterable<string, array{\Closure(self): PatchServiceCommand}>
     */
    public static function eventHandlerArgumentsWithoutHandlerProvider(): iterable
    {
        yield 'handler removed' => [static fn (self $test): PatchServiceCommand => $test->command(dataProcessing: new ServiceDataProcessingChanges(eventHandlerCommandId: null))];

        yield 'handler removed, template without one' => [
            static fn (self $test): PatchServiceCommand => $test->command(templateId: 33, dataProcessing: new ServiceDataProcessingChanges(eventHandlerCommandId: null)),
        ];
    }

    /**
     * Only the texts provided are measured against the form bounds: a longer one stored by legacy is left alone.
     */
    public function testOnlyTheProvidedTextsAreMeasuredAgainstTheFormBounds(): void
    {
        $longNote = str_repeat('n', ServiceExtendedInformations::MAX_NOTE_LENGTH + 1);
        $this->store(extendedInformations: new ServiceExtendedInformations(note: $longNote));

        $this->patch($this->command(extendedInformations: new ServiceExtendedInformationsChanges(actionUrl: 'http://ok')));
        self::assertSame($longNote, $this->written()->extendedInformations?->note);

        try {
            $this->patch($this->command(extendedInformations: new ServiceExtendedInformationsChanges(note: $longNote . 'x')));
            self::fail('A note above its form bound must be refused.');
        } catch (ServiceExtendedInformationsTooLongException $exception) {
            self::assertSame(['monitoringEngine' => ['note']], $exception->criteria);
        }
    }

    /**
     * A Centreon Monitoring Agent check command given to the service imposes its freshness over the values sent in
     * the same update; one inherited from the template does not.
     */
    public function testACentreonMonitoringAgentCommandImposesTheFreshness(): void
    {
        $this->addCommand(23, name: 'Centreon-Monitoring-Agent-Linux', fromMonitoringConnector: true);

        $this->patch($this->command(
            checkOptions: new ServiceCheckOptionsChanges(new CommandId(23)),
            dataProcessing: new ServiceDataProcessingChanges(checkFreshness: TriStateEnum::False, freshnessThreshold: 30),
        ));

        $written = $this->written();
        self::assertSame(
            [TriStateEnum::True, ServiceDataProcessing::CENTREON_MONITORING_AGENT_FRESHNESS_THRESHOLD],
            [$written->dataProcessing->checkFreshness, $written->dataProcessing->freshnessThreshold],
        );
    }

    public function testAnInheritedCentreonMonitoringAgentCommandImposesNothing(): void
    {
        $this->addCommand(23, name: 'Centreon-Monitoring-Agent-Linux', fromMonitoringConnector: true);
        $this->addTemplate(30, checkCommandId: 23);

        $this->patch($this->command(templateId: 30, dataProcessing: new ServiceDataProcessingChanges(freshnessThreshold: 30)));

        self::assertSame([TriStateEnum::UseDefault, 30], [$this->written()->dataProcessing->checkFreshness, $this->written()->dataProcessing->freshnessThreshold]);
    }

    /**
     * A service ending up notifying the contacts of its host only has none of its own: the visible ones are cleared,
     * those added by the same update included; those the actor cannot see stay linked.
     */
    public function testSwitchingToTheHostContactsClearsTheVisibleContacts(): void
    {
        $this->optionRepository->options['inheritance_mode'] = '1';
        $this->store(notifications: $this->notifications(additiveInheritance: true, contactIds: [31, 32], contactGroupIds: [41, 42]));
        $this->restrictTheActor();

        $this->patch($this->command(
            actorId: self::RESTRICTED_USER_ID,
            notifications: new ServiceNotificationsChanges(useOnlyContactsFromHost: true),
            contactIds: ListChange::add([new NotificationContactId(31)]),
        ));

        $notifications = $this->written()->notifications;
        self::assertInstanceOf(ServiceNotifications::class, $notifications);
        self::assertSame([32], $this->idValues($notifications->contactIds));
        self::assertSame([42], $this->idValues($notifications->contactGroupIds));
        self::assertFalse($notifications->contactAdditiveInheritance);
        self::assertFalse($notifications->contactGroupAdditiveInheritance);
    }

    public function testAContactAddedToAServiceUsingTheHostContactsIsNotKept(): void
    {
        $this->store(notifications: $this->notifications(useOnlyContactsFromHost: true, contactIds: [], contactGroupIds: []));

        $this->patch($this->command(contactIds: ListChange::add([new NotificationContactId(31)]), contactGroupIds: ListChange::add([new ContactGroupId(41)])));

        $this->assertNothingWritten();
    }

    public function testTheAdditiveInheritanceFlagsAreForcedOffWhenThePlatformOptionDisablesThem(): void
    {
        $this->optionRepository->options['inheritance_mode'] = '3';

        $this->patch($this->command(notifications: new ServiceNotificationsChanges(contactAdditiveInheritance: true, contactGroupAdditiveInheritance: true)));

        $this->assertNothingWritten();
    }

    public function testTheAdditiveInheritanceFlagsAreKeptWhenThePlatformOptionEnablesThem(): void
    {
        $this->optionRepository->options['inheritance_mode'] = '1';

        $this->patch($this->command(notifications: new ServiceNotificationsChanges(contactAdditiveInheritance: true)));

        self::assertTrue($this->written()->notifications?->contactAdditiveInheritance);
    }

    public function testNoneCombinedWithAnotherNotificationOptionIsRefused(): void
    {
        $this->store(notifications: new ServiceNotifications(TriStateEnum::True, $this->notifications()->contactIds, $this->notifications()->contactGroupIds, [ServiceNotificationOptionEnum::Warning]));

        /** @var list<ServiceNotificationOptionEnum> $none */
        $none = [ServiceNotificationOptionEnum::None];

        $this->expectException(ExclusiveNotificationOptionException::class);

        $this->patch($this->command(notifications: new ServiceNotificationsChanges(options: ListChange::add($none))));
    }

    public function testNullClearsAHiddenSeverity(): void
    {
        $this->seedEveryReference();
        $this->store(severityId: 71);
        $this->restrictTheActor();

        $this->patch($this->command(actorId: self::RESTRICTED_USER_ID, severityId: null));

        self::assertNull($this->written()->severityId);
    }

    /**
     * A legacy service holding several severities shows the lowest: providing it leaves a single one, a change.
     */
    public function testProvidingTheSeverityOfAServiceHoldingSeveralIsAWrite(): void
    {
        $this->seedEveryReference();
        $this->store(severityId: 70);
        $this->serviceRepository->storedSeverityIds[self::SERVICE_ID] = [70, 72];

        $this->patch($this->command(severityId: 70));

        self::assertSame(70, $this->written()->severityId?->value);
    }
}
