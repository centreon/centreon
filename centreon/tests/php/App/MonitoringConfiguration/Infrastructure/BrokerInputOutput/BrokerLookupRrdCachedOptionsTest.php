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

namespace Tests\App\MonitoringConfiguration\Infrastructure\BrokerInputOutput;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Characterization of the RRDCacheD options lookup of the local broker, through CentreonGraphStatus::getRrdCachedOptions().
 *
 * CentreonGraph and CentreonGraphNg run the very same query inline in their constructors, which open their own
 * connections on the platform database (new CentreonDB() reading the legacy constants): they cannot be pointed at the
 * test database without a production change and are not covered here. CentreonGraphStatus is instantiated without
 * its constructor, with the test connection injected in its public $pearDB property.
 *
 * Pinned current behavior:
 * - every "rrd_cached_option" / "rrd_cached" parameter of every input and output of every broker configuration
 *   (active or not, module or daemon) of every "localhost" poller is read, the last row read winning;
 * - getRrdCachedOptions() fills $this->rrdCachedOptions but RETURNS AN EMPTY ARRAY, and the constructor assigns
 *   that return value: RRDCacheD flushing is therefore never enabled for status graphs (likely bug);
 * - $this->rrdCachedOptions stays null when nothing matches.
 * The lookup is global: when a foreign "localhost" poller exists (base data or concurrent tests), only the return
 * value and the keys of the side effect are asserted.
 */
#[Group('broker_io_characterization')]
final class BrokerLookupRrdCachedOptionsTest extends KernelTestCase
{
    private const LOCALHOST_POLLER_ID = 9550;
    private const POLLER_ID = 9551;

    private BrokerTestDatabase $database;

    private BrokerFlowFixture $flows;

    private BrokerLookupScope $scope;

    protected function setUp(): void
    {
        $this->database = new BrokerTestDatabase();
        $this->flows = new BrokerFlowFixture($this->database);
        $this->scope = new BrokerLookupScope($this->database);
        $this->database->createPoller(self::LOCALHOST_POLLER_ID, localhost: true);
        $this->database->createPoller(self::POLLER_ID);
    }

    protected function tearDown(): void
    {
        $this->database->cleanUp();
        parent::tearDown();
    }

    public function testOptionsOfTheRrdOutputOfTheLocalBrokerAreReadButNotReturned(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::LOCALHOST_POLLER_ID, 'lookup-rrd-central');
        $this->addRrdOutput($configId, 'unix', '/var/lib/rrdcached/rrdcached.sock');

        $this->assertRrdCachedOptions(['rrd_cached_option' => 'unix', 'rrd_cached' => '/var/lib/rrdcached/rrdcached.sock']);
    }

    public function testOptionsStoredByLegacyWritersInAnyFlowOfAnInactiveModuleAreRead(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::LOCALHOST_POLLER_ID, 'lookup-rrd-module', daemon: false);
        $this->scope->setBrokerConfigurationActivation($configId, false);
        $this->flows->insertLegacyRows($configId, 'input', 0, BrokerFlowFixture::flatRows([
            'name' => 'not-an-rrd-flow',
            'port' => '5670',
            'rrd_cached_option' => 'tcp',
            'rrd_cached' => '42217',
            'type' => 'ipv4',
            'blockId' => '2_3',
        ]));

        $this->assertRrdCachedOptions(['rrd_cached_option' => 'tcp', 'rrd_cached' => '42217']);
    }

    public function testOnlyThePresentOptionsAreRead(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::LOCALHOST_POLLER_ID, 'lookup-rrd-partial');
        $this->flows->insertLegacyRows($configId, 'output', 0, BrokerFlowFixture::flatRows([
            'name' => 'rrd-without-socket',
            'metrics_path' => '/var/lib/centreon/metrics/',
            'rrd_cached_option' => 'disable',
            'type' => 'rrd',
            'blockId' => '1_13',
        ]));

        $this->assertRrdCachedOptions(['rrd_cached_option' => 'disable']);
    }

    public function testNonLocalhostPollersAreIgnored(): void
    {
        $configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'lookup-rrd-remote');
        $this->addRrdOutput($configId, 'unix', '/var/lib/rrdcached/remote.sock');
        $this->database->createBrokerConfiguration(self::LOCALHOST_POLLER_ID, 'lookup-rrd-central-without-rrd');

        $this->assertRrdCachedOptions(null);
    }

    private function addRrdOutput(int $configId, string $option, string $socket): void
    {
        $this->flows->addThroughApi($configId, 'output', 13, 'lookup-rrd', [
            'metrics_path' => '/var/lib/centreon/metrics/',
            'status_path' => '/var/lib/centreon/status/',
            'rrd_cached_option' => $option,
            'rrd_cached' => $socket,
            'store_in_data_bin' => 'yes',
            'write_metrics' => 'yes',
            'insert_in_index_data' => '1',
            'write_status' => 'yes',
            'filters_category' => [],
        ]);
    }

    /**
     * @param array<string, string>|null $expected the options read when no foreign data interferes
     */
    private function assertRrdCachedOptions(?array $expected): void
    {
        $graph = (new \ReflectionClass(\CentreonGraphStatus::class))->newInstanceWithoutConstructor();
        $graph->pearDB = $this->database->configuration;

        $foreignBefore = $this->scope->foreignLocalhostPollerExists();
        $returned = (new \ReflectionMethod(\CentreonGraphStatus::class, 'getRrdCachedOptions'))->invoke($graph);
        $foreign = $foreignBefore || $this->scope->foreignLocalhostPollerExists();

        self::assertSame($this->database->configuration, $graph->pearDB, 'The legacy code must use the test connection');
        self::assertSame([], $returned);

        /** @var array<string, string>|null $read */
        $read = $graph->rrdCachedOptions;
        if (! $foreign) {
            self::assertSame($expected, $read);

            return;
        }
        self::assertSame([], array_diff(array_keys($read ?? []), ['rrd_cached_option', 'rrd_cached']));
    }
}
