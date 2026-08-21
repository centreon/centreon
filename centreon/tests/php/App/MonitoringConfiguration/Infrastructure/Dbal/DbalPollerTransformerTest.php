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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\Poller\GorgoneCommunicationTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Domain\Exception\InvalidGorgoneCommunicationTypeException;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalPollerRepository;
use App\MonitoringConfiguration\Infrastructure\Dbal\DbalPollerTransformer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type RowTypeAlias from DbalPollerRepository
 */
final class DbalPollerTransformerTest extends TestCase
{
    private DbalPollerTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new DbalPollerTransformer();
    }

    public function testTransformMapsAllFields(): void
    {
        $row = $this->buildRow();

        $poller = $this->transformer->transform($row);

        self::assertSame(42, $poller->id()->value);
        self::assertSame('MyPoller', $poller->name->value);
        self::assertSame('192.168.1.1', $poller->address->value);
        self::assertTrue($poller->isCentral);
        self::assertFalse($poller->isDefault);
        self::assertTrue($poller->isActivated);
        self::assertSame(PollerTypeEnum::VM, $poller->pollerType);
        self::assertSame(123456789012345, $poller->uid->value);

        self::assertSame('/usr/sbin/centengine', $poller->engineInformation->startCommand);
        self::assertSame('/usr/sbin/centengine -s', $poller->engineInformation->stopCommand);
        self::assertSame('/usr/sbin/centengine -r', $poller->engineInformation->restartCommand);
        self::assertSame('/usr/sbin/centengine -R', $poller->engineInformation->reloadCommand);
        self::assertSame('/usr/sbin/centengine-bin', $poller->engineInformation->binaryPath);
        self::assertSame('/usr/sbin/centenginestats', $poller->engineInformation->statisticsBinaryPath);
        self::assertSame('/var/log/centreon-engine/service-perfdata', $poller->engineInformation->perfdataFilePath);

        self::assertSame('service cbd reload', $poller->brokerInformation->reloadCommand);
        self::assertSame('/etc/centreon-broker', $poller->brokerInformation->configurationPath);
        self::assertSame('/usr/share/centreon/lib/centreon-broker', $poller->brokerInformation->modulesPath);
        self::assertSame('/var/log/centreon-broker', $poller->brokerInformation->logsPath);

        self::assertSame('/usr/lib64/centreon-connector', $poller->connectorConfiguration->connectorPath);

        self::assertSame('/etc/init.d/centreontrapd', $poller->trapConfiguration->initScriptPath);
        self::assertSame('/etc/snmp/centreon_traps', $poller->trapConfiguration->snmpTrapPathConf);

        self::assertSame(GorgoneCommunicationTypeEnum::ZMQ, $poller->gorgoneConfiguration->communicationType);
        self::assertSame(5557, $poller->gorgoneConfiguration->gorgonePort);
        self::assertSame(2222, $poller->gorgoneConfiguration->sshPort);
        self::assertTrue($poller->gorgoneConfiguration->useRemoteServerAsProxy);
    }

    public function testTransformWithNullableFields(): void
    {
        $row = $this->buildRow([
            'engine_start_command' => null,
            'engine_stop_command' => null,
            'engine_restart_command' => null,
            'engine_reload_command' => null,
            'nagios_bin' => null,
            'nagiostats_bin' => null,
            'nagios_perfdata' => null,
            'broker_reload_command' => null,
            'centreonbroker_cfg_path' => null,
            'centreonbroker_module_path' => null,
            'centreonbroker_logs_path' => null,
            'centreonconnector_path' => null,
            'init_script_centreontrapd' => null,
            'snmp_trapd_path_conf' => null,
            'gorgone_port' => null,
            'ssh_port' => null,
        ]);

        $poller = $this->transformer->transform($row);

        self::assertNull($poller->engineInformation->startCommand);
        self::assertNull($poller->engineInformation->binaryPath);
        self::assertNull($poller->engineInformation->statisticsBinaryPath);
        self::assertNull($poller->brokerInformation->reloadCommand);
        self::assertNull($poller->connectorConfiguration->connectorPath);
        self::assertNull($poller->trapConfiguration->initScriptPath);
        self::assertSame(5556, $poller->gorgoneConfiguration->gorgonePort);
        self::assertSame(22, $poller->gorgoneConfiguration->sshPort);
    }

    public function testTransformDeactivatedPoller(): void
    {
        $row = $this->buildRow(['is_activated' => '0']);

        $poller = $this->transformer->transform($row);

        self::assertFalse($poller->isActivated);
    }

    public function testTransformDockerType(): void
    {
        $row = $this->buildRow(['poller_type' => 'docker']);

        $poller = $this->transformer->transform($row);

        self::assertSame(PollerTypeEnum::Docker, $poller->pollerType);
    }

    public function testTransformKeepsValidCentralAddress(): void
    {
        $row = $this->buildRow(['central_address' => 'central.example.com:8443/platform']);

        $poller = $this->transformer->transform($row);

        self::assertSame('central.example.com:8443/platform', $poller->centralAddress?->value);
    }

    public function testTransformReducesLegacySchemeCentralAddressToHostAndPort(): void
    {
        $row = $this->buildRow(['central_address' => 'https://central.example.com:8443/centreon/monitoring/resources?tab=details']);

        $poller = $this->transformer->transform($row);

        self::assertSame('central.example.com:8443', $poller->centralAddress?->value);
    }

    public function testTransformNullsUnreadableLegacyCentralAddress(): void
    {
        $row = $this->buildRow(['central_address' => 'not a valid address!']);

        $poller = $this->transformer->transform($row);

        self::assertNull($poller->centralAddress);
    }

    #[DataProvider('provideGorgoneCommunicationTypes')]
    public function testTransformMapsGorgoneCommunicationType(
        string $databaseValue,
        GorgoneCommunicationTypeEnum $expectedCommunicationType,
    ): void {
        $row = $this->buildRow(['gorgone_communication_type' => $databaseValue]);

        $poller = $this->transformer->transform($row);

        self::assertSame($expectedCommunicationType, $poller->gorgoneConfiguration->communicationType);
    }

    /**
     * @return iterable<string, array{string, GorgoneCommunicationTypeEnum}>
     */
    public static function provideGorgoneCommunicationTypes(): iterable
    {
        yield 'zmq' => ['1', GorgoneCommunicationTypeEnum::ZMQ];

        yield 'ssh' => ['2', GorgoneCommunicationTypeEnum::SSH];

        yield 'pull' => ['3', GorgoneCommunicationTypeEnum::Pull];

        yield 'pullwss' => ['4', GorgoneCommunicationTypeEnum::PullWss];
    }

    #[DataProvider('provideUnmappableGorgoneCommunicationTypes')]
    public function testTransformRejectsUnmappableGorgoneCommunicationType(string $databaseValue): void
    {
        $row = $this->buildRow(['gorgone_communication_type' => $databaseValue]);

        $this->expectException(InvalidGorgoneCommunicationTypeException::class);
        $this->expectExceptionMessage(sprintf('"%s" read from the database for poller #42', $databaseValue));

        $this->transformer->transform($row);
    }

    /**
     * Without strict mode MySQL stores the error member '' instead of rejecting a value
     * outside the enum, so that one is readable in production too.
     *
     * @return iterable<string, array{string}>
     */
    public static function provideUnmappableGorgoneCommunicationTypes(): iterable
    {
        yield 'out of range' => ['5'];

        yield 'zero' => ['0'];

        yield 'mysql error member' => [''];
    }

    /**
     * The 26.07 upgrade added the values 3 and 4 to the column and migrated every cloud
     * poller to 4 while the enum still stopped at 2, so every poller read answered an
     * HTTP 500. The three tests below keep the column and the enum in lockstep.
     */
    public function testEveryCommunicationTypeAllowedBySchemaIsMapped(): void
    {
        $mappedCases = [];
        foreach ($this->communicationTypesAllowedBySchema() as $databaseValue) {
            try {
                $mappedCases[] = $this->transformer->transform(
                    $this->buildRow(['gorgone_communication_type' => $databaseValue])
                )->gorgoneConfiguration->communicationType;
            } catch (InvalidGorgoneCommunicationTypeException) {
                self::fail(
                    "createTables.sql allows gorgone_communication_type '{$databaseValue}' "
                    . 'but GorgoneCommunicationTypeEnum has no case for it'
                );
            }
        }

        self::assertEqualsCanonicalizing(
            GorgoneCommunicationTypeEnum::cases(),
            $mappedCases,
            'nagios_server.gorgone_communication_type and GorgoneCommunicationTypeEnum have drifted apart'
        );
    }

    public function testSchemaCommentDocumentsTheMappingUsedByTheTransformer(): void
    {
        // Comparing the two sets cannot see two values swapped, and a wrong comment is
        // exactly what made 1 and 2 look inverted for a whole release.
        foreach ($this->communicationTypesDocumentedBySchema() as $databaseValue => $documentedName) {
            $communicationType = $this->transformer->transform(
                $this->buildRow(['gorgone_communication_type' => (string) $databaseValue])
            )->gorgoneConfiguration->communicationType;

            self::assertSame(
                mb_strtolower($documentedName),
                mb_strtolower($communicationType->name),
                "createTables.sql documents '{$databaseValue}: {$documentedName}' but the transformer "
                . "maps that value to {$communicationType->name}"
            );
        }
    }

    public function testNoUpgradeScriptAllowsACommunicationTypeAFreshInstallRejects(): void
    {
        $allowedByFreshInstall = $this->communicationTypesAllowedBySchema();

        foreach ($this->communicationTypesAllowedByUpgradeScripts() as $script => $allowedByUpgrade) {
            self::assertSame(
                [],
                array_values(array_diff($allowedByUpgrade, $allowedByFreshInstall)),
                "{$script} widens gorgone_communication_type beyond createTables.sql, so upgraded "
                . 'platforms would hold values a fresh install never produces'
            );
        }
    }

    /**
     * @return list<string>
     */
    private function communicationTypesAllowedBySchema(): array
    {
        return $this->enumValues($this->columnDefinitionFromSchema());
    }

    /**
     * @return array<array-key, string> database value => case name documented by the column comment
     */
    private function communicationTypesDocumentedBySchema(): array
    {
        $definition = $this->columnDefinitionFromSchema();

        self::assertSame(
            1,
            preg_match("/COMMENT '([^']+)'/i", $definition, $comment),
            'the gorgone_communication_type column of createTables.sql no longer documents its values'
        );

        preg_match_all('/(\d+)\s*:\s*(\w+)/', $comment[1], $documentedPairs, PREG_SET_ORDER);

        $documented = [];
        foreach ($documentedPairs as $documentedPair) {
            $documented[$documentedPair[1]] = $documentedPair[2];
        }

        self::assertNotSame([], $documented, "unreadable column comment: {$comment[1]}");

        return $documented;
    }

    /**
     * @return array<string, list<string>> upgrade script name => values its ALTER allows
     */
    private function communicationTypesAllowedByUpgradeScripts(): array
    {
        $allowed = [];
        foreach (glob(dirname(__DIR__, 6) . '/www/install/php/Update-*.php') ?: [] as $script) {
            $matched = preg_match(
                '/MODIFY COLUMN\s+`?gorgone_communication_type`?\s+(enum\s*\([^)]*\))/i',
                (string) file_get_contents($script),
                $definition
            );

            if ($matched === 1) {
                $allowed[basename($script)] = $this->enumValues($definition[1]);
            }
        }

        self::assertNotSame(
            [],
            $allowed,
            'no upgrade script redeclares gorgone_communication_type any more, this guard guards nothing'
        );

        return $allowed;
    }

    private function columnDefinitionFromSchema(): string
    {
        $schemaPath = dirname(__DIR__, 6) . '/www/install/createTables.sql';
        self::assertFileExists($schemaPath, "createTables.sql not found at {$schemaPath}");

        self::assertSame(
            1,
            preg_match(
                '/CREATE TABLE `nagios_server` \([^;]*?(`?gorgone_communication_type`?\s+enum\s*\([^)]*\)[^\n]*)/is',
                (string) file_get_contents($schemaPath),
                $column
            ),
            'the gorgone_communication_type column is no longer parsable in the nagios_server table'
        );

        return $column[1];
    }

    /**
     * @return list<string>
     */
    private function enumValues(string $definition): array
    {
        self::assertSame(
            1,
            preg_match('/enum\s*\(([^)]*)\)/i', $definition, $enum),
            "no enum definition found in: {$definition}"
        );

        preg_match_all("/'([^']*)'/", $enum[1], $values);

        return $values[1];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @phpstan-return RowTypeAlias
     */
    private function buildRow(array $overrides = []): array
    {
        /** @var RowTypeAlias */
        return array_merge([
            'poller_id' => 42,
            'poller_name' => 'MyPoller',
            'poller_address' => '192.168.1.1',
            'is_central' => '1',
            'is_default' => 0,
            'is_activated' => '1',
            'poller_type' => 'vm',
            'poller_uid' => 123456789012345,
            'gorgone_communication_type' => '1',
            'gorgone_port' => 5557,
            'ssh_port' => 2222,
            'remote_server_use_as_proxy' => '1',
            'engine_start_command' => '/usr/sbin/centengine',
            'engine_stop_command' => '/usr/sbin/centengine -s',
            'engine_restart_command' => '/usr/sbin/centengine -r',
            'engine_reload_command' => '/usr/sbin/centengine -R',
            'nagios_bin' => '/usr/sbin/centengine-bin',
            'nagiostats_bin' => '/usr/sbin/centenginestats',
            'nagios_perfdata' => '/var/log/centreon-engine/service-perfdata',
            'broker_reload_command' => 'service cbd reload',
            'centreonbroker_cfg_path' => '/etc/centreon-broker',
            'centreonbroker_module_path' => '/usr/share/centreon/lib/centreon-broker',
            'centreonbroker_logs_path' => '/var/log/centreon-broker',
            'centreonconnector_path' => '/usr/lib64/centreon-connector',
            'init_script_centreontrapd' => '/etc/init.d/centreontrapd',
            'snmp_trapd_path_conf' => '/etc/snmp/centreon_traps',
        ], $overrides);
    }
}
