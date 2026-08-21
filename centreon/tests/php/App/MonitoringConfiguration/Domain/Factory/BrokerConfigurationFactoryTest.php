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

namespace Tests\App\MonitoringConfiguration\Domain\Factory;

use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerFlowGroupEnum;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerInputOutput;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerLoggerEnum;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerLogLevelEnum;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerStreamTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\CentralAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Factory\BrokerConfigurationFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Oracle: the legacy "Add wizard" poller path — a single {slug}-module config (daemon=0) with one
 * central-module output flow. On-prem uses an IPv4 output; cloud uses a BBDO Client output.
 */
final class BrokerConfigurationFactoryTest extends TestCase
{
    private const ON_PREM = false;
    private const CLOUD = true;
    private const ON_PREM_CENTRAL_ADDRESS = '10.1.2.3';
    private const CLOUD_CENTRAL_ADDRESS = 'staging.euwest1.centreon.click/funky-donkey';
    private const CLOUD_BROKER_HOST = 'broker-funky-donkey-staging.euwest1.centreon.click';

    private BrokerConfigurationFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new BrokerConfigurationFactory();
    }

    public function testCreateDefaultBuildsModuleHeader(): void
    {
        $config = $this->factory->createDefault(new PollerId(7), 'My Poller', self::ON_PREM, $this->onPremAddress());

        self::assertSame(7, $config->pollerId->value);
        self::assertSame('my-poller-module', $config->name->value);
        self::assertSame('my-poller-module.json', $config->fileName->value);
        self::assertTrue($config->isActivated);
        self::assertFalse($config->daemon);
        self::assertFalse($config->configWriteTimestamp);
        self::assertFalse($config->configWriteThreadId);
        self::assertSame(100000, $config->eventQueueMaxSize);
        self::assertSame('', $config->commandFile);
        self::assertSame('/var/lib/centreon-engine', $config->cacheDirectory);
        self::assertSame('/var/log/centreon-broker', $config->logDirectory);
        self::assertTrue($config->statsActivate);
    }

    public function testSlugifyLowercasesAndReplacesSpaces(): void
    {
        $config = $this->factory->createDefault(
            new PollerId(1),
            'My Remote Poller',
            self::ON_PREM,
            $this->onPremAddress(),
        );

        self::assertSame('my-remote-poller-module', $config->name->value);
        self::assertSame('my-remote-poller-module.json', $config->fileName->value);
    }

    public function testOnPremUsesIpv4CentralModuleOutput(): void
    {
        $config = $this->factory->createDefault(new PollerId(1), 'Poller', self::ON_PREM, $this->onPremAddress());

        self::assertCount(1, $config->flows);
        $flow = $config->flows->toArray()[0];
        self::assertSame(BrokerFlowGroupEnum::Output, $flow->group);
        self::assertSame(0, $flow->groupId);
        // The stream kind is typed; its type/blockId rows are derived at persistence, not carried here.
        self::assertSame(BrokerStreamTypeEnum::Ipv4, $flow->type);

        self::assertSame([
            'name' => 'central-module-master-output',
            'port' => '5669',
            'host' => self::ON_PREM_CENTRAL_ADDRESS,
            'failover' => '',
            'retry_interval' => '15',
            'buffering_timeout' => '0',
            'protocol' => 'bbdo',
            'tls' => 'no',
            'private_key' => '',
            'public_cert' => '',
            'ca_certificate' => '',
            'negotiation' => 'yes',
            'one_peer_retention_mode' => 'no',
            'compression' => 'no',
            'compression_level' => '',
            'compression_buffer' => '',
        ], $this->params($flow));
    }

    public function testCloudUsesBbdoClientCentralModuleOutputWithAuthorizationToken(): void
    {
        $config = $this->factory->createDefault(
            new PollerId(1),
            'Poller',
            self::CLOUD,
            $this->cloudAddress(),
            'the-token',
        );

        self::assertCount(1, $config->flows);
        $flow = $config->flows->toArray()[0];
        self::assertSame(BrokerFlowGroupEnum::Output, $flow->group);
        self::assertSame(0, $flow->groupId);
        self::assertSame(BrokerStreamTypeEnum::BbdoClient, $flow->type);

        self::assertSame([
            'name' => 'central-module-master-output',
            'host' => self::CLOUD_BROKER_HOST,
            'port' => '443',
            'retry_interval' => '',
            'transport_protocol' => 'gRPC',
            'authorization' => 'the-token',
            'encryption' => 'yes',
            'ca_certificate' => '',
            'ca_name' => '',
            'compression' => 'no',
        ], $this->params($flow));
    }

    /**
     * The central address is the *web* entry point, so it may carry a web port and a base path.
     * Neither belongs in the IPv4 output's host: the broker dials its own port (5669).
     */
    #[DataProvider('onPremCentralAddressProvider')]
    public function testOnPremUsesTheBareHostAsOutputHost(string $centralAddress, string $expectedHost): void
    {
        $config = $this->factory->createDefault(
            new PollerId(1),
            'Poller',
            self::ON_PREM,
            new CentralAddress($centralAddress),
        );

        self::assertSame(BrokerStreamTypeEnum::Ipv4, $config->flows->toArray()[0]->type);
        self::assertSame($expectedHost, $this->params($config->flows->toArray()[0])['host']);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function onPremCentralAddressProvider(): iterable
    {
        yield 'IPv4 address' => ['10.0.0.1', '10.0.0.1'];

        // An unbracketed IPv6 address must not have its last group mistaken for a port.
        yield 'IPv6 address' => ['2001:db8::1', '2001:db8::1'];

        yield 'web port is stripped' => ['central.example.com:8443', 'central.example.com'];

        yield 'base path is stripped' => ['central.example.com/centreon', 'central.example.com'];

        yield 'web port and base path are stripped' => ['central.example.com:8443/base/path', 'central.example.com'];
    }

    public function testCloudResolvesBrokerGatewayHostFromCentralAddress(): void
    {
        $config = $this->factory->createDefault(
            new PollerId(1),
            'Poller',
            self::CLOUD,
            new CentralAddress('acme.useast2.centreon.cloud/happy-otter'),
            'the-token',
        );

        self::assertSame(BrokerStreamTypeEnum::BbdoClient, $config->flows->toArray()[0]->type);
        self::assertSame(
            'broker-happy-otter-acme.useast2.centreon.cloud',
            $this->params($config->flows->toArray()[0])['host'],
        );
    }

    public function testCloudIgnoresTheWebPortWhenResolvingBrokerGatewayHost(): void
    {
        $config = $this->factory->createDefault(
            new PollerId(1),
            'Poller',
            self::CLOUD,
            new CentralAddress('acme.useast2.centreon.cloud:8443/happy-otter'),
            'the-token',
        );

        $params = $this->params($config->flows->toArray()[0]);
        self::assertSame('broker-happy-otter-acme.useast2.centreon.cloud', $params['host']);
        // The gateway is always dialled on 443, whatever port the web entry point uses.
        self::assertSame('443', $params['port']);
    }

    #[DataProvider('unresolvableCloudCentralAddressProvider')]
    public function testCloudRejectsCentralAddressWithoutASinglePlatformSegment(string $centralAddress): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->factory->createDefault(
            new PollerId(1),
            'Poller',
            self::CLOUD,
            new CentralAddress($centralAddress),
            'the-token',
        );
    }

    /**
     * Addresses that are valid {@see CentralAddress} values but carry no derivable cloud gateway
     * host. A protocol scheme cannot appear here at all — CentralAddress rejects it upfront.
     *
     * @return iterable<string, array{0: string}>
     */
    public static function unresolvableCloudCentralAddressProvider(): iterable
    {
        yield 'no platform base path' => ['acme.useast2.centreon.cloud'];

        yield 'single-label host' => ['acme/happy-otter'];

        // A nested path would otherwise yield "broker-team/poller-acme.useast2.centreon.cloud".
        yield 'nested platform path' => ['acme.useast2.centreon.cloud/team/poller'];
    }

    /**
     * The single-platform-segment rule belongs to the cloud gateway derivation, not to the address
     * itself: on-prem these are perfectly usable.
     */
    #[DataProvider('unresolvableCloudCentralAddressProvider')]
    public function testOnPremAcceptsAddressesTheCloudBranchRejects(string $centralAddress): void
    {
        $config = $this->factory->createDefault(
            new PollerId(1),
            'Poller',
            self::ON_PREM,
            new CentralAddress($centralAddress),
        );

        self::assertSame(BrokerStreamTypeEnum::Ipv4, $config->flows->toArray()[0]->type);
        self::assertNotSame('', $this->params($config->flows->toArray()[0])['host']);
    }

    public function testDefaultLogsAreCoreInfoRestError(): void
    {
        $config = $this->factory->createDefault(new PollerId(1), 'Poller', self::ON_PREM, $this->onPremAddress());

        self::assertCount(18, $config->logs);

        $levels = [];
        foreach ($config->logs as $log) {
            $levels[$log->logger->value] = $log->level;
        }

        self::assertSame(BrokerLogLevelEnum::Info, $levels[BrokerLoggerEnum::Core->value]);
        self::assertSame(BrokerLogLevelEnum::Error, $levels[BrokerLoggerEnum::Sql->value]);
        self::assertSame(BrokerLogLevelEnum::Error, $levels[BrokerLoggerEnum::EventScript->value]);

        $infoCount = count(array_filter($levels, static fn (BrokerLogLevelEnum $level): bool => $level === BrokerLogLevelEnum::Info));
        $errorCount = count(array_filter($levels, static fn (BrokerLogLevelEnum $level): bool => $level === BrokerLogLevelEnum::Error));
        self::assertSame(1, $infoCount);
        self::assertSame(17, $errorCount);
    }

    private function onPremAddress(): CentralAddress
    {
        return new CentralAddress(self::ON_PREM_CENTRAL_ADDRESS);
    }

    private function cloudAddress(): CentralAddress
    {
        return new CentralAddress(self::CLOUD_CENTRAL_ADDRESS);
    }

    /**
     * @return array<string, string>
     */
    private function params(BrokerInputOutput $flow): array
    {
        $params = [];
        foreach ($flow->parameters as $parameter) {
            $params[$parameter->configKey] = $parameter->configValue;
            self::assertSame(0, $parameter->groupLevel);
        }

        return $params;
    }
}
