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

use App\MonitoringConfiguration\Application\Command\CheckOptionsChanges;
use App\MonitoringConfiguration\Application\Command\DataProcessingChanges;
use App\MonitoringConfiguration\Application\Command\ExtendedInformationsChanges;
use App\MonitoringConfiguration\Application\Command\NotificationsChanges;
use App\MonitoringConfiguration\Application\Command\PatchHostTemplateCommand;
use App\MonitoringConfiguration\Application\Command\PatchHostTemplateCommandHandler;
use App\MonitoringConfiguration\Application\Command\SchedulingOptionsChanges;
use App\MonitoringConfiguration\Domain\Aggregate\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Event\HostTemplateServicesCleanupRequested;
use App\MonitoringConfiguration\Domain\Event\HostTemplateUpdated;
use App\MonitoringConfiguration\Domain\Event\HostTemplateVaultPurgeRequested;
use App\MonitoringConfiguration\Domain\Exception\CommandNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostSeverityNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateLockedException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\MediaNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimePeriodNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimezoneNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Domain\Repository\OptionRepository;
use App\MonitoringConfiguration\Domain\Repository\TimePeriodRepository;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\VaultInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostSeverityRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeMediaRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeOptionRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimePeriodRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTimezoneRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;
use Tests\App\Shared\Double\EventBusSpy;
use Tests\App\Shared\Double\FakeVault;
use Tests\App\Shared\Infrastructure\Messenger\Double\LoggerSpy;

/**
 * @phpstan-type ChangesTypeAlias = array{
 *   name?: HostTemplateName,
 *   alias?: HostAlias,
 *   timezoneId?: TimezoneId,
 *   severityId?: ?HostSeverityId,
 *   snmpCommunity?: ?string,
 *   checkOptions?: CheckOptionsChanges,
 *   extendedInformations?: ExtendedInformationsChanges,
 *   schedulingOptions?: SchedulingOptionsChanges,
 *   dataProcessing?: DataProcessingChanges,
 *   notifications?: NotificationsChanges,
 * }
 */
final class PatchHostTemplateCommandHandlerTest extends KernelTestCase
{
    private const VAULTED_COMMUNITY = 'secret::vault::monitoring/hosts/tpl-uuid::_HOSTSNMPCOMMUNITY';

    private PatchHostTemplateCommandHandler $handler;

    private FakeHostTemplateRepository $repository;

    private FakeCommandRepository $commandRepository;

    private FakeHostSeverityRepository $hostSeverityRepository;

    private FakeOptionRepository $optionRepository;

    private FakeResourceAccessRepository $resourceAccessRepository;

    private FakeVault $vault;

    private EventBusSpy $eventBus;

    private LoggerSpy $logger;

    protected function setUp(): void
    {
        $container = self::getContainer();

        $this->repository = new FakeHostTemplateRepository();
        $this->commandRepository = new FakeCommandRepository();
        $this->hostSeverityRepository = new FakeHostSeverityRepository();
        $this->optionRepository = new FakeOptionRepository();
        $this->resourceAccessRepository = new FakeResourceAccessRepository();
        $this->vault = new FakeVault();
        $this->vault->vaultEnabled = false;

        $this->eventBus = new EventBusSpy();
        $this->logger = new LoggerSpy();

        $container->set(HostTemplateRepository::class, $this->repository);
        $container->set(CommandRepository::class, $this->commandRepository);
        $container->set(HostSeverityRepository::class, $this->hostSeverityRepository);
        $container->set(TimezoneRepository::class, new FakeTimezoneRepository());
        $container->set(TimePeriodRepository::class, new FakeTimePeriodRepository());
        $container->set(MediaRepository::class, new FakeMediaRepository());
        $container->set(OptionRepository::class, $this->optionRepository);
        $container->set(ResourceAccessRepository::class, $this->resourceAccessRepository);
        $container->set(VaultInterface::class, $this->vault);
        $container->set(VaultCredentialWriter::class, new VaultCredentialWriter($this->vault));
        $container->set(EventBus::class, $this->eventBus);
        $container->set(LoggerInterface::class, $this->logger);

        /** @var PatchHostTemplateCommandHandler $handler */
        $handler = $container->get(PatchHostTemplateCommandHandler::class);
        $this->handler = $handler;
    }

    public function testAnUnknownTemplateIsNotFoundByItsId(): void
    {
        try {
            ($this->handler)($this->command(404, []));
            self::fail('An unknown template must not be found.');
        } catch (HostTemplateNotFoundException $exception) {
            self::assertSame(['id' => [404]], $exception->criteria);
        }
    }

    public function testALockedTemplateIsRefusedBeforeAnyReferenceIsLookedUp(): void
    {
        $this->addTemplate(isLocked: true);

        $this->expectException(HostTemplateLockedException::class);

        ($this->handler)($this->command(7, ['timezoneId' => new TimezoneId(404)]));
    }

    /**
     * @param ChangesTypeAlias $change
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('provideUnknownReferences')]
    public function testAnUnknownReferenceIsRefused(array $change, string $exception, bool $restricted = false): void
    {
        $this->addTemplate();
        $this->hostSeverityRepository->hostSeverities[8] = new HostSeverityName('Critical');
        if ($restricted) {
            $this->resourceAccessRepository->hostSeverityScope = AccessScope::restrictedTo(HostSeverityId::class, []);
        }

        $this->expectException($exception);

        ($this->handler)($this->command(7, $change, restricted: $restricted));
    }

    /**
     * @return iterable<string, array{0: ChangesTypeAlias, 1: class-string<\Throwable>, 2?: bool}>
     */
    public static function provideUnknownReferences(): iterable
    {
        yield 'timezone' => [['timezoneId' => new TimezoneId(404)], TimezoneNotFoundException::class];

        yield 'severity' => [['severityId' => new HostSeverityId(404)], HostSeverityNotFoundException::class];

        yield 'severity out of the actor scope' => [['severityId' => new HostSeverityId(8)], HostSeverityNotFoundException::class, true];

        yield 'icon' => [
            ['extendedInformations' => new ExtendedInformationsChanges(iconId: new MediaId(404))],
            MediaNotFoundException::class,
        ];

        yield 'check time period' => [
            ['schedulingOptions' => new SchedulingOptionsChanges(checkTimeperiodId: new TimePeriodId(404))],
            TimePeriodNotFoundException::class,
        ];

        yield 'notification time period' => [
            ['notifications' => new NotificationsChanges(periodId: new TimePeriodId(404))],
            TimePeriodNotFoundException::class,
        ];

        yield 'event handler command' => [
            ['dataProcessing' => new DataProcessingChanges(eventHandlerCommandId: new CommandId(404))],
            CommandNotFoundException::class,
        ];

        yield 'check command' => [
            ['checkOptions' => new CheckOptionsChanges(checkCommandId: new CommandId(404))],
            CommandNotFoundException::class,
        ];
    }

    public function testAChangeIsSavedAndFiredAsALoggedUpdate(): void
    {
        $this->addTemplate();

        $template = ($this->handler)($this->command(7, ['alias' => new HostAlias('New alias')]));

        self::assertSame('New alias', $template->alias?->value);
        self::assertSame([7], $this->repository->updatedIds);
        $events = $this->eventBus->getDispatchedEvents(HostTemplateUpdated::class);
        self::assertCount(1, $events);
        self::assertTrue($events[0]->loggable);
        self::assertSame(5, $events[0]->creatorId);
        self::assertSame([HostTemplateUpdated::class], $this->dispatchedClasses());
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(HostTemplateServicesCleanupRequested::class));
    }

    public function testNothingChangedSavesAndFiresNothing(): void
    {
        $this->addTemplate();

        ($this->handler)($this->command(7, ['name' => new HostTemplateName('tpl'), 'alias' => new HostAlias('Alias'), 'notifications' => new NotificationsChanges(interval: 30)]));

        self::assertSame([], $this->repository->updatedIds);
        self::assertSame([], $this->dispatchedClasses());
    }

    public function testANameUsedByAHostIsRefused(): void
    {
        $this->addTemplate();
        $this->repository->hostNames = ['taken'];

        $this->expectException(HostTemplateAlreadyExistsException::class);

        ($this->handler)($this->command(7, ['name' => new HostTemplateName('taken')]));
    }

    public function testANameUsedByAnotherTemplateIsRefused(): void
    {
        $this->addTemplate();
        $this->repository->hostTemplates[8] = new HostTemplate(new HostTemplateId(8), new HostTemplateName('taken'));

        $this->expectException(HostTemplateAlreadyExistsException::class);

        ($this->handler)($this->command(7, ['name' => new HostTemplateName('taken')]));
    }

    public function testTheTemplateOwnNameIsAccepted(): void
    {
        $this->addTemplate();

        ($this->handler)($this->command(7, ['name' => new HostTemplateName('tpl'), 'alias' => new HostAlias('Other')]));

        self::assertSame([7], $this->repository->updatedIds);
    }

    public function testAMonitoringAgentCommandForcesTheFreshness(): void
    {
        $this->addTemplate();
        $this->commandRepository->commands[9] = new Command(
            new CommandId(9),
            new CommandName('Centreon-Monitoring-Agent-check'),
            CommandTypeEnum::Check,
            new CommandLine('$USER1$/check'),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: true,
            connector: null,
            comment: null,
        );

        $template = ($this->handler)($this->command(7, ['checkOptions' => new CheckOptionsChanges(checkCommandId: new CommandId(9))]));

        self::assertSame(TriStateEnum::True, $template->dataProcessing->checkFreshness);
        self::assertSame(DataProcessing::CENTREON_MONITORING_AGENT_FRESHNESS_THRESHOLD, $template->dataProcessing->freshnessThreshold);
    }

    public function testTheAdditiveInheritanceFlagsAreDroppedWhenTheOptionIsDisabled(): void
    {
        $this->addTemplate();
        $this->optionRepository->options['inheritance_mode'] = '3';

        $template = ($this->handler)($this->command(7, ['notifications' => new NotificationsChanges(contactAdditiveInheritance: true)]));

        self::assertFalse($template->notifications?->contactAdditiveInheritance);
    }

    public function testClearingASeverityTheActorCannotSeeKeepsIt(): void
    {
        $this->addTemplate();
        $this->resourceAccessRepository->hostSeverityScope = AccessScope::restrictedTo(HostSeverityId::class, []);

        $template = ($this->handler)($this->command(7, ['severityId' => null, 'alias' => new HostAlias('Other')], restricted: true));

        self::assertSame(8, $template->severityId?->value);
    }

    public function testClearingAVisibleSeverityRemovesIt(): void
    {
        $this->addTemplate();
        $this->hostSeverityRepository->hostSeverities[8] = new HostSeverityName('Critical');

        $template = ($this->handler)($this->command(7, ['severityId' => null]));

        self::assertNull($template->severityId);
    }

    public function testACommunitySentWithAVaultIsWrittenToTheTemplateEntryAndCounts(): void
    {
        $this->vault->vaultEnabled = true;
        $this->addTemplate(snmpCommunity: self::VAULTED_COMMUNITY);
        $this->vault->writtenPaths['_HOSTSNMPCOMMUNITY'] = self::VAULTED_COMMUNITY;

        $template = ($this->handler)($this->command(7, [], snmpCommunity: 'new-community'));

        self::assertSame(self::VAULTED_COMMUNITY, $template->snmpCommunity?->value);
        self::assertSame('tpl-uuid', $this->vault->writeManyCalls[0]['uuid']);
        self::assertSame(['_HOSTSNMPCOMMUNITY' => 'new-community'], $this->vault->writeManyCalls[0]['secrets']);
        self::assertSame([7], $this->repository->updatedIds);
    }

    public function testAVaultReferenceSentAsCommunityIsIgnored(): void
    {
        $this->vault->vaultEnabled = true;
        $this->addTemplate(snmpCommunity: self::VAULTED_COMMUNITY);

        ($this->handler)($this->command(7, [], snmpCommunity: 'secret::vault::monitoring/hosts/other::_HOSTSNMPCOMMUNITY'));

        self::assertSame([], $this->vault->writeManyCalls);
        self::assertSame([], $this->repository->updatedIds);
    }

    public function testClearingAVaultedCommunityClearsItsKeyAndPurgesTheEntryAfterTheSave(): void
    {
        $this->vault->vaultEnabled = true;
        $this->addTemplate(snmpCommunity: self::VAULTED_COMMUNITY);

        $template = ($this->handler)($this->command(7, [], snmpCommunity: null));

        self::assertNull($template->snmpCommunity);
        self::assertSame(['_HOSTSNMPCOMMUNITY'], $this->vault->writeManyCalls[0]['deletes']);
        $purges = $this->eventBus->getDispatchedEvents(HostTemplateVaultPurgeRequested::class);
        self::assertCount(1, $purges);
        self::assertTrue($purges[0]->bestEffort);
        self::assertSame(self::VAULTED_COMMUNITY, $purges[0]->hostTemplate->snmpCommunity?->value);
    }

    public function testWithoutAVaultTheCommunityIsStoredAsSent(): void
    {
        $this->addTemplate();

        $template = ($this->handler)($this->command(7, [], snmpCommunity: 'private'));

        self::assertSame('private', $template->snmpCommunity?->value);
        self::assertSame([], $this->vault->writeManyCalls);
    }

    public function testAVaultFailureSavesNothing(): void
    {
        $this->vault->vaultEnabled = true;
        $this->vault->writeThrows = true;
        $this->addTemplate();

        try {
            ($this->handler)($this->command(7, ['alias' => new HostAlias('Other')], snmpCommunity: 'new-community'));
            self::fail('A vault failure must stop the update.');
        } catch (VaultWriteFailedException) {
        }

        self::assertSame([], $this->repository->updatedIds);
        self::assertSame([], $this->dispatchedClasses());
    }

    public function testAFailedSavePurgesTheVaultEntryItMinted(): void
    {
        $this->vault->vaultEnabled = true;
        $this->addTemplate();
        $this->repository->updateThrows = new \RuntimeException('The save failed.');

        try {
            ($this->handler)($this->command(7, [], snmpCommunity: 'new-community'));
            self::fail('The save failure must reach the caller.');
        } catch (\RuntimeException $exception) {
            self::assertSame('The save failed.', $exception->getMessage());
        }

        self::assertSame(['new-uuid'], array_column($this->vault->deleteCalls, 'uuid'));
        self::assertSame([], $this->logger->warningMessages);
        self::assertSame([], $this->dispatchedClasses());
    }

    public function testAFailedSaveAfterWritingTheExistingEntryLogsTheDivergence(): void
    {
        $this->vault->vaultEnabled = true;
        $this->addTemplate(snmpCommunity: self::VAULTED_COMMUNITY);
        $this->vault->writtenPaths['_HOSTSNMPCOMMUNITY'] = self::VAULTED_COMMUNITY;
        $this->repository->updateThrows = new \RuntimeException('The save failed.');

        try {
            ($this->handler)($this->command(7, [], snmpCommunity: 'new-community'));
            self::fail('The save failure must reach the caller.');
        } catch (\RuntimeException $exception) {
            self::assertSame('The save failed.', $exception->getMessage());
        }

        self::assertSame([], $this->vault->deleteCalls);
        self::assertCount(1, $this->logger->warningMessages);
        self::assertSame(7, $this->logger->warningMessages[0]['context']['host_template_id'] ?? null);
    }

    public function testAFailedSaveAfterClearingTheVaultedCommunityLogsTheDivergence(): void
    {
        $this->vault->vaultEnabled = true;
        $this->addTemplate(snmpCommunity: self::VAULTED_COMMUNITY);
        $this->repository->updateThrows = new \RuntimeException('The save failed.');

        try {
            ($this->handler)($this->command(7, [], snmpCommunity: null));
            self::fail('The save failure must reach the caller.');
        } catch (\RuntimeException) {
        }

        self::assertSame([], $this->vault->deleteCalls);
        self::assertCount(1, $this->logger->warningMessages);
        self::assertSame([], $this->dispatchedClasses());
    }

    public function testAFailingPurgeDoesNotHideTheCause(): void
    {
        $this->vault->vaultEnabled = true;
        $this->vault->deleteThrows = true;
        $this->addTemplate();
        $this->repository->updateThrows = new \RuntimeException('The save failed.');

        try {
            ($this->handler)($this->command(7, [], snmpCommunity: 'new-community'));
            self::fail('The save failure must reach the caller.');
        } catch (\RuntimeException $exception) {
            self::assertSame('The save failed.', $exception->getMessage());
        }

        self::assertCount(1, $this->vault->deleteCalls);
        self::assertCount(1, $this->logger->warningMessages);
    }

    public function testAFailedSaveWithoutAVaultWriteTouchesNoVault(): void
    {
        $this->vault->vaultEnabled = true;
        $this->addTemplate(snmpCommunity: self::VAULTED_COMMUNITY);
        $this->repository->updateThrows = new \RuntimeException('The save failed.');

        try {
            ($this->handler)($this->command(7, ['alias' => new HostAlias('Other')]));
            self::fail('The save failure must reach the caller.');
        } catch (\RuntimeException) {
        }

        self::assertSame([], $this->vault->deleteCalls);
        self::assertSame([], $this->logger->warningMessages);
    }

    /**
     * @param ChangesTypeAlias $changes the named arguments of the command
     */
    private function command(int $id, array $changes, string|null|false $snmpCommunity = false, bool $restricted = false): PatchHostTemplateCommand
    {
        if ($snmpCommunity !== false) {
            $changes['snmpCommunity'] = $snmpCommunity;
        }

        return new PatchHostTemplateCommand(
            new HostTemplateId($id),
            $restricted ? TestActingUsers::restricted(5) : TestActingUsers::unrestricted(5),
            ...$changes,
        );
    }

    private function addTemplate(bool $isLocked = false, string $snmpCommunity = 'public'): void
    {
        $this->repository->hostTemplates[7] = new HostTemplate(
            new HostTemplateId(7),
            new HostTemplateName('tpl'),
            new CheckOptions(null),
            alias: new HostAlias('Alias'),
            isLocked: $isLocked,
            snmpCommunity: new SnmpCommunity($snmpCommunity),
            severityId: new HostSeverityId(8),
            notifications: Notifications::default()->with(enabled: TriStateEnum::True, interval: 30),
        );
    }

    /**
     * @return list<class-string>
     */
    private function dispatchedClasses(): array
    {
        return array_values(array_unique(array_merge(
            $this->eventBus->getDispatchedEvents(HostTemplateUpdated::class) !== [] ? [HostTemplateUpdated::class] : [],
            $this->eventBus->getDispatchedEvents(HostTemplateVaultPurgeRequested::class) !== [] ? [HostTemplateVaultPurgeRequested::class] : [],
        )));
    }
}
