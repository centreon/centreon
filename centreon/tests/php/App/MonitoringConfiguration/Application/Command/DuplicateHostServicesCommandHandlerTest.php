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

use App\MonitoringConfiguration\Application\Command\DuplicateHostServicesCommand;
use App\MonitoringConfiguration\Application\Command\DuplicateHostServicesCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Event\ServiceCreated;
use App\MonitoringConfiguration\Domain\Event\ServiceDuplicated;
use App\MonitoringConfiguration\Domain\Exception\UnlicensedServiceTemplatesException;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Application\Command\NonTransactionalCommand;
use App\Shared\Application\Vault\VaultCredentialReader;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Vault\VaultPathEnum;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;
use Tests\App\Shared\Double\EventBusSpy;
use Tests\App\Shared\Double\FakeVault;
use Tests\App\Shared\Infrastructure\Legacy\Double\RecordingLogger;

final class DuplicateHostServicesCommandHandlerTest extends TestCase
{
    private const SOURCE_HOST_ID = 5;
    private const COPY_HOST_ID = 9;
    private const ACTOR_ID = 42;

    private FakeServiceRepository $serviceRepository;

    private FakeResourceAccessRepository $resourceAccessRepository;

    /** @var list<int> the service templates the license no longer allows, as evaluated before the host copy */
    private array $unlicensedServiceTemplateIds = [];

    private FakeVault $vault;

    private EventBusSpy $eventBus;

    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->serviceRepository = new FakeServiceRepository();
        $this->resourceAccessRepository = new FakeResourceAccessRepository();
        $this->vault = new FakeVault();
        // Off by default: the values then copy verbatim; the vault tests turn it on.
        $this->vault->vaultEnabled = false;

        $this->eventBus = new EventBusSpy();
        $this->logger = new RecordingLogger();
    }

    public function testTheCommandIsTransactional(): void
    {
        self::assertFalse((new \ReflectionClass(DuplicateHostServicesCommand::class))->implementsInterface(NonTransactionalCommand::class));
    }

    public function testASharedServiceIsReLinkedAndAnExclusiveOneCloned(): void
    {
        $exclusive = $this->seedService(1, 'exclusive', templateId: 30);
        $this->seedService(2, 'shared');
        $this->serviceRepository->sharedServiceIds = [2];

        $this->duplicate();

        self::assertSame([['serviceId' => 2, 'hostId' => self::COPY_HOST_ID]], $this->serviceRepository->addedHostLinks);
        $clones = $this->clones();
        self::assertCount(1, $clones);
        self::assertNotSame(1, $clones[0]->id()->value);
        self::assertSame('exclusive', $clones[0]->name->value);
        self::assertSame(30, $clones[0]->templateId?->value);
        self::assertSame([true, 3], [$clones[0]->hostActivated, $clones[0]->hostPollerId?->value]);
        self::assertSame(self::SOURCE_HOST_ID, $exclusive->hostId->value);
    }

    public function testAServiceGoneBeforeItIsLoadedIsNotCloned(): void
    {
        $this->seedService(1, 'gone');
        $this->seedService(2, 'kept');
        $this->serviceRepository->goneBeforeLoadIds = [1];

        $this->duplicate();

        self::assertSame(['kept'], array_map(static fn (Service $clone): string => $clone->name->value, $this->clones()));
    }

    public function testTheCloneCarriesTheSourceMacros(): void
    {
        $this->seedService(1, 'ping', macros: [
            new ServiceMacro(new ServiceMacroName('PORT'), '80', isPassword: false, description: 'the port', id: new ServiceMacroId(7)),
            new ServiceMacro(new ServiceMacroName('EMPTY'), '', isPassword: true, id: new ServiceMacroId(8)),
        ]);

        $this->duplicate();

        $clone = $this->clones()[0];
        self::assertSame(2, $clone->checkOptions->checkCommandId?->value);
        self::assertSame(['80'], $clone->checkOptions->args);
        self::assertSame(
            [['PORT', '80', false, null, null], ['EMPTY', '', true, null, null]],
            array_map(
                static fn (ServiceMacro $macro): array => [$macro->name->value, $macro->value, $macro->isPassword, $macro->description, $macro->id],
                $clone->checkOptions->macros,
            ),
        );
    }

    public function testAVaultedPasswordIsReMintedIntoANewEntry(): void
    {
        $this->vault->vaultEnabled = true;
        $reference = $this->reference('src-uuid', 'PASSWORD');
        $this->vault->resolved[$reference] = 's3cr3t';
        $this->seedService(1, 'ping', macros: [new ServiceMacro(new ServiceMacroName('PASSWORD'), $reference, isPassword: true)]);

        $this->duplicate();

        self::assertSame(
            [[
                'customPath' => VaultPathEnum::MonitoringServices->value,
                'secrets' => ['_SERVICEPASSWORD' => 's3cr3t'],
                'uuid' => null,
                'deletes' => [],
            ]],
            $this->vault->writeManyCalls,
        );
        self::assertSame($this->reference('new-uuid', 'PASSWORD'), $this->clones()[0]->checkOptions->macros[0]->value);
        self::assertSame($reference, $this->serviceRepository->services[1]->checkOptions->macros[0]->value);
    }

    public function testWithTheVaultOffTheValuesAreCopiedAsIs(): void
    {
        $reference = $this->reference('src-uuid', 'PASSWORD');
        $this->seedService(1, 'ping', macros: [
            new ServiceMacro(new ServiceMacroName('PASSWORD'), $reference, isPassword: true),
            new ServiceMacro(new ServiceMacroName('PLAIN'), 's3cr3t', isPassword: true),
        ]);

        $this->duplicate();

        self::assertSame(
            [$reference, 's3cr3t'],
            array_map(static fn (ServiceMacro $macro): string => $macro->value, $this->clones()[0]->checkOptions->macros),
        );
        self::assertSame([], $this->vault->writeManyCalls);
    }

    public function testWithTheVaultOnAPlaintextPasswordIsCopiedAsIs(): void
    {
        $this->vault->vaultEnabled = true;
        $this->seedService(1, 'ping', macros: [new ServiceMacro(new ServiceMacroName('PLAIN'), 's3cr3t', isPassword: true)]);

        $this->duplicate();

        self::assertSame('s3cr3t', $this->clones()[0]->checkOptions->macros[0]->value);
        self::assertSame([], $this->vault->writeManyCalls);
    }

    public function testOnlyTheVaultedPasswordsAreReMinted(): void
    {
        $this->vault->vaultEnabled = true;
        $reference = $this->reference('src-uuid', 'VAULTED');
        $this->vault->resolved[$reference] = 's3cr3t';
        $this->seedService(1, 'ping', macros: [
            new ServiceMacro(new ServiceMacroName('VAULTED'), $reference, isPassword: true),
            new ServiceMacro(new ServiceMacroName('PLAIN'), 'plain-secret', isPassword: true),
        ]);

        $this->duplicate();

        self::assertSame([['_SERVICEVAULTED' => 's3cr3t']], array_column($this->vault->writeManyCalls, 'secrets'));
        self::assertSame(
            [$this->reference('new-uuid', 'VAULTED'), 'plain-secret'],
            array_map(static fn (ServiceMacro $macro): string => $macro->value, $this->clones()[0]->checkOptions->macros),
        );
    }

    public function testEveryPasswordMacroOfTheSameNamePointsToTheNewEntry(): void
    {
        $this->vault->vaultEnabled = true;
        $reference = $this->reference('src-uuid', 'PASS');
        $this->vault->resolved[$reference] = 's3cr3t';
        $this->seedService(1, 'ping', macros: [
            new ServiceMacro(new ServiceMacroName('PASS'), $reference, isPassword: true),
            new ServiceMacro(new ServiceMacroName('PASS'), $reference, isPassword: true),
        ]);

        $this->duplicate();

        self::assertSame(
            [$this->reference('new-uuid', 'PASS'), $this->reference('new-uuid', 'PASS')],
            array_map(static fn (ServiceMacro $macro): string => $macro->value, $this->clones()[0]->checkOptions->macros),
        );
    }

    public function testAVaultWriteFailureFailsEveryService(): void
    {
        $this->givenTwoVaultedServices();
        $this->vault->writeThrows = true;

        $exception = $this->duplicateFailing();

        self::assertInstanceOf(VaultWriteFailedException::class, $exception);
        self::assertSame('Unable to write vault credential', $exception->getPrevious()?->getMessage());
        self::assertSame([], $this->clones());
    }

    public function testAnUnresolvableReferenceFailsEveryService(): void
    {
        $this->givenTwoVaultedServices();
        $this->vault->resolveThrows = true;

        $exception = $this->duplicateFailing();

        self::assertInstanceOf(VaultWriteFailedException::class, $exception);
        self::assertSame('Unable to resolve vault credential', $exception->getPrevious()?->getMessage());
        self::assertSame([], $this->vault->writeManyCalls);
    }

    public function testTheEntriesMintedBeforeAFailureArePurged(): void
    {
        $this->givenTwoVaultedServices();
        $this->vault->writeThrowsOnCall = 2;

        $exception = $this->duplicateFailing();

        self::assertInstanceOf(VaultWriteFailedException::class, $exception);
        self::assertSame(
            [['customPath' => VaultPathEnum::MonitoringServices->value, 'uuid' => 'new-uuid']],
            $this->vault->deleteCalls,
        );
    }

    public function testTheEntriesMintedForServicesThatFailToBeWrittenArePurged(): void
    {
        $this->givenTwoVaultedServices();
        $this->vault->writtenPaths = [
            '_SERVICEFIRST' => $this->reference('uuid-1', 'FIRST'),
            '_SERVICESECOND' => $this->reference('uuid-2', 'SECOND'),
        ];
        $this->serviceRepository->addThrows = new \RuntimeException('Unable to write the service');

        $exception = $this->duplicateFailing();

        self::assertSame('Unable to write the service', $exception->getMessage());
        // Every secret is minted before the first service is written: both entries are purged.
        self::assertSame(
            [
                ['customPath' => VaultPathEnum::MonitoringServices->value, 'uuid' => 'uuid-1'],
                ['customPath' => VaultPathEnum::MonitoringServices->value, 'uuid' => 'uuid-2'],
            ],
            $this->vault->deleteCalls,
        );
    }

    public function testAPurgeFailureNeverReplacesTheCause(): void
    {
        $this->givenTwoVaultedServices();
        $this->vault->writeThrowsOnCall = 2;
        $this->vault->deleteThrows = true;

        $exception = $this->duplicateFailing();

        self::assertInstanceOf(VaultWriteFailedException::class, $exception);
        $warning = $this->logger->lastRecord();
        self::assertNotNull($warning);
        self::assertSame('warning', $warning['level']);
        self::assertSame(self::SOURCE_HOST_ID, $warning['context']['source_host_id'] ?? null);
        self::assertSame(self::COPY_HOST_ID, $warning['context']['new_host_id'] ?? null);
        self::assertSame('new-uuid', $warning['context']['vault_uuid'] ?? null);
    }

    public function testAFailingLoggerNeverReplacesTheCauseEither(): void
    {
        $this->givenTwoVaultedServices();
        $this->vault->writeThrowsOnCall = 2;
        $this->vault->deleteThrows = true;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('warning')->willThrowException(new \RuntimeException('The logger failed.'));

        $exception = $this->duplicateFailing($logger);

        self::assertInstanceOf(VaultWriteFailedException::class, $exception);
    }

    public function testAnUnlicensedServiceTemplateRefusesBeforeAnyWrite(): void
    {
        $this->vault->vaultEnabled = true;
        $reference = $this->reference('src-uuid', 'PASSWORD');
        $this->vault->resolved[$reference] = 's3cr3t';
        $this->seedService(1, 'licensed', macros: [new ServiceMacro(new ServiceMacroName('PASSWORD'), $reference, isPassword: true)]);
        $this->seedService(2, 'unlicensed', templateId: 30);
        $this->seedService(3, 'shared');
        $this->serviceRepository->sharedServiceIds = [3];
        $this->unlicensedServiceTemplateIds = [30];

        $exception = $this->duplicateFailing();

        self::assertInstanceOf(UnlicensedServiceTemplatesException::class, $exception);
        self::assertSame(['serviceTemplateId' => 30], $exception->criteria);
        self::assertSame([], $this->clones());
        self::assertSame([], $this->serviceRepository->addedHostLinks);
        self::assertSame([], $this->vault->writeManyCalls);
        self::assertSame([], $this->resourceAccessRepository->duplicatedHostServiceAccess);
    }

    public function testEachCloneFiresServiceDuplicatedForTheActor(): void
    {
        $this->seedService(1, 'ping');
        $this->seedService(2, 'http');
        $this->seedService(3, 'shared');
        $this->serviceRepository->sharedServiceIds = [3];

        $this->duplicate();

        $events = $this->eventBus->getDispatchedEvents(ServiceDuplicated::class);
        $aggregates = array_map(static fn (ServiceDuplicated $event): Service|array => $event->aggregate, $events);
        self::assertSame($this->clones(), $aggregates);
        self::assertSame([self::ACTOR_ID, self::ACTOR_ID], array_map(static fn (ServiceDuplicated $event): int => $event->creatorId, $events));
        self::assertTrue($this->eventBus->shouldNotHaveDispatched(ServiceCreated::class));
    }

    public function testTheAccessOfTheCopysServicesIsDuplicatedOnceTheyAreAllLinked(): void
    {
        $this->seedService(1, 'ping');
        $this->seedService(2, 'shared');
        $this->serviceRepository->sharedServiceIds = [2];

        $this->duplicate();

        self::assertCount(1, $this->clones());
        self::assertCount(1, $this->serviceRepository->addedHostLinks);
        self::assertSame(
            [['sourceHostId' => self::SOURCE_HOST_ID, 'newHostId' => self::COPY_HOST_ID]],
            $this->resourceAccessRepository->duplicatedHostServiceAccess,
        );
    }

    public function testTheAccessIsNotDuplicatedWhenAServiceFails(): void
    {
        $this->givenTwoVaultedServices();
        $this->vault->writeThrowsOnCall = 2;

        $this->duplicateFailing();

        self::assertSame([], $this->resourceAccessRepository->duplicatedHostServiceAccess);
    }

    private function duplicate(?LoggerInterface $logger = null): void
    {
        ($this->handler($logger))(new DuplicateHostServicesCommand(
            sourceHostId: new HostId(self::SOURCE_HOST_ID),
            newHostId: new HostId(self::COPY_HOST_ID),
            duplicatedBy: new UserId(self::ACTOR_ID),
            unlicensedServiceTemplateIds: new Collection(
                array_map(static fn (int $id): ServiceTemplateId => new ServiceTemplateId($id), $this->unlicensedServiceTemplateIds),
                ServiceTemplateId::class,
            ),
        ));
    }

    private function duplicateFailing(?LoggerInterface $logger = null): \Throwable
    {
        try {
            $this->duplicate($logger);
        } catch (\Throwable $exception) {
            return $exception;
        }

        self::fail('The services step was expected to fail.');
    }

    private function handler(?LoggerInterface $logger): DuplicateHostServicesCommandHandler
    {
        return new DuplicateHostServicesCommandHandler(
            $this->serviceRepository,
            $this->resourceAccessRepository,
            $this->vault,
            new VaultCredentialReader($this->vault),
            new VaultCredentialWriter($this->vault),
            $this->eventBus,
            $logger ?? $this->logger,
        );
    }

    /**
     * Two exclusive services, each with a password in its own vault entry, minted in this order.
     */
    private function givenTwoVaultedServices(): void
    {
        $this->vault->vaultEnabled = true;
        foreach ([1 => 'FIRST', 2 => 'SECOND'] as $id => $name) {
            $reference = $this->reference("src-uuid-{$id}", $name);
            $this->vault->resolved[$reference] = "secret-{$id}";
            $this->seedService($id, mb_strtolower($name), macros: [new ServiceMacro(new ServiceMacroName($name), $reference, isPassword: true)]);
        }
    }

    /**
     * @param list<ServiceMacro> $macros
     */
    private function seedService(int $id, string $name, ?int $templateId = null, array $macros = []): Service
    {
        $service = new Service(
            id: new ServiceId($id),
            name: new ServiceName($name),
            hostId: new HostId(self::SOURCE_HOST_ID),
            hostActivated: true,
            hostPollerId: new PollerId(3),
            templateId: $templateId !== null ? new ServiceTemplateId($templateId) : null,
            checkOptions: new ServiceCheckOptions(new CommandId(2), ['80'], $macros),
        );
        $this->serviceRepository->services[$id] = $service;

        return $service;
    }

    /**
     * @return list<Service> the services written on the copy, in the order they were written
     */
    private function clones(): array
    {
        return array_values(array_filter(
            $this->serviceRepository->services,
            static fn (Service $service): bool => $service->hostId->value === self::COPY_HOST_ID,
        ));
    }

    private function reference(string $uuid, string $name): string
    {
        return "secret::vault::monitoring/services/{$uuid}::_SERVICE{$name}";
    }
}
