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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use ApiPlatform\Metadata\Delete;
use App\MonitoringConfiguration\Application\Command\DeleteHostCommand;
use App\MonitoringConfiguration\Application\Command\DeleteHostResult;
use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\DeleteHostProcessor;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Tests\App\Shared\Double\FakeVault;

final class DeleteHostProcessorVaultPurgeTest extends TestCase
{
    public function testItDoesNotPurgeAnythingWhenNothingIsVaulted(): void
    {
        $vault = new FakeVault();
        $this->process($vault, new NullLogger(), $this->host(), []);

        self::assertSame([], $vault->deleteCalls);
    }

    public function testItPurgesTheHostsVaultEntryWhenOneExists(): void
    {
        $vault = new FakeVault();
        $vault->extractedUuids['secret::vault::monitoring/hosts/uuid-1::_HOSTSNMPCOMMUNITY'] = 'uuid-1';
        $host = $this->host(macros: [
            new HostMacro(new HostMacroName('token'), 'secret::vault::monitoring/hosts/uuid-1::_HOSTSNMPCOMMUNITY', isPassword: true),
        ]);

        $this->process($vault, new NullLogger(), $host, []);

        self::assertSame([['customPath' => 'monitoring/hosts', 'uuid' => 'uuid-1']], $vault->deleteCalls);
    }

    public function testItPurgesEachServicesVaultEntry(): void
    {
        $vault = new FakeVault();
        $vault->extractedUuids['secret::vault::monitoring/services/uuid-2::_SERVICETOKEN'] = 'uuid-2';
        $service = $this->service(macros: [
            new ServiceMacro(new ServiceMacroName('token'), 'secret::vault::monitoring/services/uuid-2::_SERVICETOKEN', isPassword: true),
        ]);

        $this->process($vault, new NullLogger(), $this->host(), [$service]);

        self::assertSame([['customPath' => 'monitoring/services', 'uuid' => 'uuid-2']], $vault->deleteCalls);
    }

    public function testAFailedPurgeIsLoggedAndDoesNotThrow(): void
    {
        $vault = new FakeVault();
        $vault->extractedUuids['secret::vault::monitoring/hosts/uuid-3::_HOSTSNMPCOMMUNITY'] = 'uuid-3';
        $vault->deleteThrows = true;
        $host = $this->host(macros: [
            new HostMacro(new HostMacroName('token'), 'secret::vault::monitoring/hosts/uuid-3::_HOSTSNMPCOMMUNITY', isPassword: true),
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Failed to purge a vault entry after deletion',
                self::callback(static fn (array $context): bool => $context['vault_path'] === 'monitoring/hosts' && $context['uuid'] === 'uuid-3'),
            );

        // The whole point of purging after the command bus returns: a purge failure must not
        // surface as a request failure, the deletion itself already succeeded.
        $this->process($vault, $logger, $host, []);
    }

    /**
     * @param list<Service> $deletedServices
     */
    private function process(FakeVault $vault, LoggerInterface $logger, Host $host, array $deletedServices): void
    {
        $result = new DeleteHostResult($host, new Collection($deletedServices, Service::class));

        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->method('execute')
            ->with(self::isInstanceOf(DeleteHostCommand::class))
            ->willReturn($result);

        $security = $this->createMock(Security::class);
        $security->method('getUser')
            ->willReturn(new CredentialUser(new Credential(new CredentialIdentifier('admin'), new UserId(1), active: true)));

        $processor = new DeleteHostProcessor(
            commandBus: $commandBus,
            security: $security,
            vault: $vault,
            vaultCredentialWriter: new VaultCredentialWriter($vault),
            logger: $logger,
        );

        $processor->process(null, new Delete(), ['id' => $host->id()->value]);
    }

    /**
     * @param list<HostMacro> $macros
     */
    private function host(array $macros = []): Host
    {
        return new Host(
            id: new HostId(1),
            name: new HostName('server-01'),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            checkOptions: new CheckOptions(null, macros: $macros),
        );
    }

    /**
     * @param list<ServiceMacro> $macros
     */
    private function service(array $macros = []): Service
    {
        return new Service(
            id: new ServiceId(2),
            name: new ServiceName('ping'),
            hostId: new HostId(1),
            macros: $macros,
        );
    }
}
