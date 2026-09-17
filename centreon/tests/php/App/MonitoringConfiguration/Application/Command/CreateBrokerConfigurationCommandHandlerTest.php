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

use App\MonitoringConfiguration\Application\Command\CreateBrokerConfigurationCommand;
use App\MonitoringConfiguration\Application\Command\CreateBrokerConfigurationCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerStreamTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\CentralAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Factory\BrokerConfigurationFactory;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeBrokerConfigurationRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeVault;

final class CreateBrokerConfigurationCommandHandlerTest extends TestCase
{
    private const ON_PREM_CENTRAL_ADDRESS = '10.1.2.3';
    private const CLOUD_CENTRAL_ADDRESS = 'staging.euwest1.centreon.click/funky-donkey';
    private const CLOUD_BROKER_HOST = 'broker-funky-donkey-staging.euwest1.centreon.click';

    public function testOnPremPersistsIpv4BrokerConfigurationWithoutResolvingToken(): void
    {
        $repository = new FakeBrokerConfigurationRepository();
        $vault = new FakeVault();
        // Left null on purpose: on-prem must not query the central token (would throw here).
        $handler = new CreateBrokerConfigurationCommandHandler(
            $repository, new BrokerConfigurationFactory(), $vault, false,
        );

        $handler($this->onPremCommand());

        self::assertCount(1, $repository->brokerConfigurations);

        $config = array_values($repository->brokerConfigurations)[0];
        self::assertSame('my-poller-module', $config->name->value);
        self::assertSame(BrokerStreamTypeEnum::Ipv4, $config->flows->toArray()[0]->type);
        self::assertSame([], $vault->writeCalls);
    }

    public function testOnPremWritesCentralAddressAsOutputHost(): void
    {
        $repository = new FakeBrokerConfigurationRepository();
        $vault = new FakeVault();
        $handler = new CreateBrokerConfigurationCommandHandler(
            $repository, new BrokerConfigurationFactory(), $vault, false,
        );

        $handler($this->onPremCommand());

        $config = array_values($repository->brokerConfigurations)[0];
        self::assertSame(self::ON_PREM_CENTRAL_ADDRESS, $this->outputValue($config, 'host'));
    }

    public function testCloudWithPlaintextCentralTokenUsesItVerbatim(): void
    {
        $repository = new FakeBrokerConfigurationRepository();
        $repository->centralBbdoServerAuthorizationToken = 'central-token';

        $vault = new FakeVault();

        $handler = new CreateBrokerConfigurationCommandHandler(
            $repository, new BrokerConfigurationFactory(), $vault, true,
        );

        $handler($this->cloudCommand());

        $config = array_values($repository->brokerConfigurations)[0];
        self::assertSame(BrokerStreamTypeEnum::BbdoClient, $config->flows->toArray()[0]->type);
        // Not a vault path → used as-is, no vault interaction.
        self::assertSame('central-token', $this->outputValue($config, 'authorization'));
        self::assertSame([], $vault->writeCalls);
    }

    public function testCloudWithVaultDisabledUsesTokenVerbatim(): void
    {
        // Central token looks like a vault path, but vault is not eligible (flag off / no config):
        // it must be used verbatim, with no resolve or re-vault attempt.
        $vaultPath = 'secret::vault::configuration/broker/central-uuid::central-master-output_authorization';

        $repository = new FakeBrokerConfigurationRepository();
        $repository->centralBbdoServerAuthorizationToken = $vaultPath;

        $vault = new FakeVault();
        $vault->vaultEnabled = false;

        $handler = new CreateBrokerConfigurationCommandHandler(
            $repository, new BrokerConfigurationFactory(), $vault, true,
        );

        $handler($this->cloudCommand());

        $config = array_values($repository->brokerConfigurations)[0];
        self::assertSame($vaultPath, $this->outputValue($config, 'authorization'));
        self::assertSame([], $vault->writeCalls);
    }

    public function testCloudWithVaultedCentralTokenResolvesThenReVaults(): void
    {
        $centralPath = 'secret::vault::configuration/broker/central-uuid::central-master-output_authorization';
        $newPath = 'secret::vault::configuration/broker/new-uuid::central-module-master-output_authorization';

        $repository = new FakeBrokerConfigurationRepository();
        $repository->centralBbdoServerAuthorizationToken = $centralPath;

        $vault = new FakeVault();
        $vault->resolved[$centralPath] = 'the-real-token';
        $vault->writtenPaths['central-module-master-output_authorization'] = $newPath;

        $handler = new CreateBrokerConfigurationCommandHandler(
            $repository, new BrokerConfigurationFactory(), $vault, true,
        );

        $handler($this->cloudCommand());

        // The persisted output references the poller's own vault entry, never the plaintext.
        $config = array_values($repository->brokerConfigurations)[0];
        self::assertSame($newPath, $this->outputValue($config, 'authorization'));
        self::assertNotSame('the-real-token', $this->outputValue($config, 'authorization'));

        // Re-vault happened under a fresh UUID (null) with the broker path + conventional key.
        self::assertCount(1, $vault->writeCalls);
        self::assertSame(BrokerConfigurationFactory::BROKER_VAULT_CUSTOM_PATH, $vault->writeCalls[0]['customPath']);
        self::assertSame('central-module-master-output_authorization', $vault->writeCalls[0]['key']);
        self::assertSame('the-real-token', $vault->writeCalls[0]['value']);
        self::assertNull($vault->writeCalls[0]['uuid']);
    }

    public function testCloudDoesNotPersistWhenResolveFails(): void
    {
        $centralPath = 'secret::vault::configuration/broker/central-uuid::central-master-output_authorization';
        $repository = new FakeBrokerConfigurationRepository();
        $repository->centralBbdoServerAuthorizationToken = $centralPath;

        $vault = new FakeVault();
        $vault->resolveThrows = true;

        $handler = new CreateBrokerConfigurationCommandHandler(
            $repository, new BrokerConfigurationFactory(), $vault, true,
        );

        $this->expectException(\RuntimeException::class);

        try {
            $handler($this->cloudCommand(new PollerId(1), 'Poller'));
        } finally {
            self::assertCount(0, $repository->brokerConfigurations);
        }
    }

    public function testCloudDoesNotPersistWhenReVaultFails(): void
    {
        $centralPath = 'secret::vault::configuration/broker/central-uuid::central-master-output_authorization';
        $repository = new FakeBrokerConfigurationRepository();
        $repository->centralBbdoServerAuthorizationToken = $centralPath;

        $vault = new FakeVault();
        $vault->resolved[$centralPath] = 'the-real-token';
        $vault->writeThrows = true;

        $handler = new CreateBrokerConfigurationCommandHandler(
            $repository, new BrokerConfigurationFactory(), $vault, true,
        );

        $this->expectException(\RuntimeException::class);

        try {
            $handler($this->cloudCommand(new PollerId(1), 'Poller'));
        } finally {
            self::assertCount(0, $repository->brokerConfigurations);
        }
    }

    public function testCloudDoesNotPersistWhenCentralHasNoToken(): void
    {
        $repository = new FakeBrokerConfigurationRepository();
        $vault = new FakeVault();
        // centralBbdoServerAuthorizationToken left null → getCentralBbdoServerAuthorizationToken() throws.
        $handler = new CreateBrokerConfigurationCommandHandler(
            $repository, new BrokerConfigurationFactory(), $vault, true,
        );

        $this->expectException(\RuntimeException::class);

        try {
            $handler($this->cloudCommand(new PollerId(1), 'Poller'));
        } finally {
            self::assertCount(0, $repository->brokerConfigurations);
        }
    }

    public function testCloudWritesBrokerGatewayHostDerivedFromCentralAddress(): void
    {
        $repository = new FakeBrokerConfigurationRepository();
        $repository->centralBbdoServerAuthorizationToken = 'central-token';

        $handler = new CreateBrokerConfigurationCommandHandler(
            $repository, new BrokerConfigurationFactory(), new FakeVault(), true,
        );

        $handler($this->cloudCommand());

        $config = array_values($repository->brokerConfigurations)[0];
        self::assertSame(BrokerStreamTypeEnum::BbdoClient, $config->flows->toArray()[0]->type);
        self::assertSame(self::CLOUD_BROKER_HOST, $this->outputValue($config, 'host'));
        self::assertSame('443', $this->outputValue($config, 'port'));
    }

    public function testCloudDoesNotPersistWhenCentralAddressHasNoPlatformPath(): void
    {
        $repository = new FakeBrokerConfigurationRepository();
        $repository->centralBbdoServerAuthorizationToken = 'central-token';

        $handler = new CreateBrokerConfigurationCommandHandler(
            $repository, new BrokerConfigurationFactory(), new FakeVault(), true,
        );

        $this->expectException(\InvalidArgumentException::class);

        try {
            // A cloud Central is always served under a platform base path: without one there is no
            // gateway host to dial, so nothing must be persisted.
            $handler(new CreateBrokerConfigurationCommand(
                pollerId: new PollerId(42),
                pollerName: 'My Poller',
                centralAddress: new CentralAddress('staging.euwest1.centreon.click'),
            ));
        } finally {
            self::assertCount(0, $repository->brokerConfigurations);
        }
    }

    private function onPremCommand(): CreateBrokerConfigurationCommand
    {
        return new CreateBrokerConfigurationCommand(
            pollerId: new PollerId(42),
            pollerName: 'My Poller',
            centralAddress: new CentralAddress(self::ON_PREM_CENTRAL_ADDRESS),
        );
    }

    private function cloudCommand(?PollerId $pollerId = null, string $pollerName = 'My Poller'): CreateBrokerConfigurationCommand
    {
        return new CreateBrokerConfigurationCommand(
            pollerId: $pollerId ?? new PollerId(42),
            pollerName: $pollerName,
            centralAddress: new CentralAddress(self::CLOUD_CENTRAL_ADDRESS),
        );
    }

    private function outputValue(BrokerConfiguration $config, string $key): ?string
    {
        foreach ($config->flows->toArray()[0]->parameters as $parameter) {
            if ($parameter->configKey === $key) {
                return $parameter->configValue;
            }
        }

        return null;
    }
}
