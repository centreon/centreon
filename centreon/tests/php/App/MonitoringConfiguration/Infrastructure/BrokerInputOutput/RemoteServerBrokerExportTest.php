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

use Centreon\Infrastructure\CentreonLegacyDB\CentreonDBAdapter;
use ConfigGenerateRemote\Backend;
use ConfigGenerateRemote\Manifest;
use ConfigGenerateRemote\Relations\BrokerInfo;
use PHPUnit\Framework\Attributes\Group;
use Pimple\Container;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Remote server configuration export (www/class/config-generate-remote) of the broker inputs/outputs.
 *
 * The central exports the stored inputs/outputs of the exported broker configurations as an infile, which the
 * remote server imports with LOAD DATA LOCAL INFILE (CentreonRemote ConfigurationExporter::import(), clauses from
 * the manifest). Both servers run the same version, so the round trip must be lossless: every column, the raw
 * JSON text of the parameters included, NULL and empty strings kept apart.
 *
 * The real import statement needs local_infile enabled on the database server: when it is not, the real
 * import test is skipped and the infile is checked by a reader implementing the LOAD DATA rules for the
 * manifest clauses (enclosed fields, backslash escapes, \N as NULL).
 */
#[Group('broker_io_characterization')]
final class RemoteServerBrokerExportTest extends KernelTestCase
{
    private const POLLER_ID = 9600;
    private const OTHER_POLLER_ID = 9601;
    private const SCRATCH_TABLE = 'broker_io_remote_import_9600';
    private const COLUMNS = ['config_id', 'tag', 'input_output_id', 'name', 'type_id', 'type', 'parameters'];

    /** Values hard to carry through an infile: separators, quotes, escapes, control characters, unicode. */
    private const TRICKY_VALUES = [
        'quotes' => 'say "hello" \'world\'',
        'trailing_quote' => 'ends with a quote"',
        'quote_then_field_separator' => 'a"~~~b',
        'backslashes' => 'C:\\path\\to\\file \\ \\\\ \\N \\n \\t \\"',
        'null_marker' => '\\N',
        'tab' => "before\tafter",
        'newlines' => "line 1\nline 2\r\nline 3",
        'unicode' => 'é è ü 中文 😀 – “quoted”',
        'field_separator' => 'a~~~b~~~',
        'line_separator' => 'a######b######',
        'json_like' => '{"key": ["value", {}], "empty": {}}',
        'empty' => '',
        'spaces' => '  padded  ',
    ];

    private BrokerTestDatabase $database;

    private BrokerFlowFixture $flows;

    private string $exportDirectory;

    private mixed $originalBackendInstance;

    private int $configId;

    private int $otherConfigId;

    protected function setUp(): void
    {
        $this->database = new BrokerTestDatabase();
        $this->flows = new BrokerFlowFixture($this->database);
        $this->database->createPoller(self::POLLER_ID);
        $this->database->createPoller(self::OTHER_POLLER_ID);
        $this->configId = $this->database->createBrokerConfiguration(self::POLLER_ID, 'broker-io-remote-export-module', daemon: false);
        $this->otherConfigId = $this->database->createBrokerConfiguration(self::OTHER_POLLER_ID, 'broker-io-remote-export-other');

        $this->exportDirectory = sys_get_temp_dir() . '/broker-io-remote-export-' . bin2hex(random_bytes(6));
        if (! mkdir($this->exportDirectory . '/configuration', 0770, true)) {
            throw new \RuntimeException('Cannot create ' . $this->exportDirectory);
        }

        // The export classes are singletons built on Backend::getInstance(): point it to the test database.
        $instanceProperty = new \ReflectionProperty(Backend::class, 'instance');
        $this->originalBackendInstance = $instanceProperty->getValue();
        $backend = (new \ReflectionClass(Backend::class))->newInstanceWithoutConstructor();
        $backend->db = $this->database->configuration;
        $backend->dbCs = $this->database->realtime;
        (new \ReflectionProperty(Backend::class, 'fullPath'))->setValue($backend, $this->exportDirectory);
        $instanceProperty->setValue(null, $backend);
    }

    protected function tearDown(): void
    {
        $this->manifestInstance()->clean();
        (new \ReflectionProperty(Backend::class, 'instance'))->setValue(null, $this->originalBackendInstance);
        array_map('unlink', glob($this->exportDirectory . '/configuration/*') ?: []);
        rmdir($this->exportDirectory . '/configuration');
        rmdir($this->exportDirectory);
        $this->database->configuration->prepare('DROP TEMPORARY TABLE IF EXISTS ' . self::SCRATCH_TABLE)->execute();
        $this->database->cleanUp();
        parent::tearDown();
    }

    public function testInfileIsDeclaredInTheManifestForTheInputOutputTable(): void
    {
        $this->seedFlows();

        $this->export();

        $data = $this->manifest()['import']['data'];
        self::assertArrayNotHasKey('cfg_centreonbroker_info.infile', $data);
        self::assertSame(
            [
                'filename' => 'cfg_centreonbroker_input_output.infile',
                'type' => 'infile',
                'table' => 'cfg_centreonbroker_input_output',
                'columns' => self::COLUMNS,
            ],
            $data['cfg_centreonbroker_input_output.infile'] ?? null
        );
    }

    public function testInfileHoldsTheStoredRowsOfTheExportedConfigurationOnly(): void
    {
        $this->seedFlows();

        $infile = $this->export();

        self::assertNotEmpty($this->storedRows('cfg_centreonbroker_input_output', $this->otherConfigId));
        self::assertSame($this->storedRows('cfg_centreonbroker_input_output', $this->configId), $this->readInfile($infile));
    }

    public function testInfileEscapesNullsEmptyStringsAndJson(): void
    {
        $this->flows->insertLegacyRows($this->configId, 'input', 0, BrokerFlowFixture::flatRows([
            'name' => 'a "quoted" \\ name',
            'quotes' => 'x"y\\z',
        ]));

        $infile = (string) file_get_contents($this->export());

        $statement = $this->database->configuration->prepare(
            "SELECT parameters FROM cfg_centreonbroker_input_output WHERE config_id = :configId AND tag = 'input'"
        );
        $statement->bindValue(':configId', $this->configId, \PDO::PARAM_INT);
        $statement->execute();
        $parameters = $statement->fetchColumn();
        self::assertIsString($parameters);
        $escapedParameters = strtr($parameters, ['\\' => '\\\\', '"' => '\\"']);
        self::assertSame(
            '"' . $this->configId . '"~~~"input"~~~"0"~~~"a \\"quoted\\" \\\\ name"~~~\\N~~~\\N~~~"'
            . $escapedParameters . '"######',
            explode('######', $infile)[0] . '######'
        );
        self::assertStringContainsString('\\\\\\"y\\\\\\\\z', $escapedParameters, 'JSON escapes must be escaped again');
    }

    public function testRoundTripThroughTheRemoteImportIsLossless(): void
    {
        $statement = $this->database->configuration->prepare('SELECT @@GLOBAL.local_infile');
        $statement->execute();
        $localInfile = $statement->fetchColumn();
        if ((string) $localInfile !== '1') {
            self::markTestSkipped(
                'local_infile is disabled on the test database server: LOAD DATA LOCAL INFILE cannot run, '
                . 'the infile content is covered by the other tests of this class.'
            );
        }
        $this->seedFlows();
        $infile = $this->export();

        // Same statement as ConfigurationExporter::import(), with the clauses the remote reads from the
        // manifest, into a scratch copy of the table (the real one already holds the exported rows).
        $manifest = $this->manifest();
        $data = $manifest['import']['data']['cfg_centreonbroker_input_output.infile'];
        $this->database->configuration->prepare(
            'CREATE TEMPORARY TABLE ' . self::SCRATCH_TABLE . ' LIKE cfg_centreonbroker_input_output'
        )->execute();
        (new CentreonDBAdapter($this->database->configuration))->loadDataInfile(
            $infile,
            self::SCRATCH_TABLE,
            $manifest['import']['infile_clauses']['fields_clause'],
            $manifest['import']['infile_clauses']['lines_clause'],
            $data['columns']
        );

        self::assertSame([], $this->storedRows(self::SCRATCH_TABLE, $this->otherConfigId));
        $imported = $this->storedRows(self::SCRATCH_TABLE, $this->configId);
        self::assertSame($this->storedRows('cfg_centreonbroker_input_output', $this->configId), $imported);
        self::assertSame($this->expectedParameters(), array_map(
            static fn (array $row): mixed => json_decode((string) $row['parameters'], true, flags: JSON_THROW_ON_ERROR),
            $imported
        ));
    }

    /**
     * Inputs/outputs of the exported configuration and of another configuration which must not be exported.
     */
    private function seedFlows(): void
    {
        $this->flows->insertLegacyRows($this->configId, 'input', 0, BrokerFlowFixture::flatRows([
            'name' => 'central-module-input "é"',
            ...self::TRICKY_VALUES,
            'type' => 'ipv4',
            'blockId' => '2_3',
        ]));
        // No type and no blockId: type and type_id stay NULL; no parameter: empty JSON object.
        $this->flows->insertLegacyRows($this->configId, 'input', 1, BrokerFlowFixture::flatRows([
            'name' => 'untyped',
        ]));
        // Only filters, no name row: empty name.
        $this->flows->insertLegacyRows($this->configId, 'output', 0, [
            ['key' => 'filters', 'value' => '', 'subgrp_id' => 1],
            ['key' => 'category', 'value' => 'neb', 'grp_level' => 1, 'parent_grp_id' => 1],
            ['key' => 'category', 'value' => "bbdo\t\"quoted\"", 'grp_level' => 1, 'parent_grp_id' => 1],
        ]);
        $this->flows->insertLegacyRows($this->configId, 'output', 3, [
            ...BrokerFlowFixture::flatRows([
                'name' => 'lua',
                'path' => '/usr/share/centreon-broker/lua/stream "connector".lua',
                'type' => 'lua',
                'blockId' => '1_33',
            ]),
            ['key' => 'lua_parameter__name', 'value' => 'json', 'fieldIndex' => 0],
            ['key' => 'lua_parameter__value', 'value' => '{"a": "b\\"c", "list": [], "object": {}}', 'fieldIndex' => 0],
            ['key' => 'lua_parameter__type', 'value' => 'string', 'fieldIndex' => 0],
            ['key' => 'lua_parameter__name', 'value' => 'multiline', 'fieldIndex' => 1],
            ['key' => 'lua_parameter__value', 'value' => "first\n\"second\"\\\n\tthird ######", 'fieldIndex' => 1],
            ['key' => 'lua_parameter__type', 'value' => 'string', 'fieldIndex' => 1],
        ]);
        $this->flows->insertLegacyRows($this->otherConfigId, 'output', 0, BrokerFlowFixture::flatRows([
            'name' => 'not-exported',
            'type' => 'ipv4',
            'blockId' => '1_3',
        ]));
    }

    /**
     * Runs the export of the test broker configurations as Broker::generate() does for the remote server pollers.
     *
     * @return string the infile path
     */
    private function export(): string
    {
        $exporter = new BrokerInfo(new Container());
        $exporter->reset(true);
        $exporter->getBrokerInfoByConfigId($this->configId);
        $exporter->closeFile();

        $file = $this->exportDirectory . '/configuration/cfg_centreonbroker_input_output.infile';
        self::assertFileExists($file);

        return $file;
    }

    private function manifestInstance(): Manifest
    {
        $manifest = Manifest::getInstance(new Container());
        self::assertInstanceOf(Manifest::class, $manifest);

        return $manifest;
    }

    /**
     * @return array{import: array{
     *     infile_clauses: array{fields_clause: array<string, string>, lines_clause: array<string, string>},
     *     data: array<string, array{filename: string, type: string, table: string, columns: list<string>}>,
     * }} the manifest as the remote server reads it (JSON encoded then decoded)
     */
    private function manifest(): array
    {
        /** @var array{import: array{
         *     infile_clauses: array{fields_clause: array<string, string>, lines_clause: array<string, string>},
         *     data: array<string, array{filename: string, type: string, table: string, columns: list<string>}>,
         * }} $manifest
         */
        $manifest = json_decode(
            json_encode($this->manifestInstance()->getManifest(), JSON_THROW_ON_ERROR),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        return $manifest;
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function storedRows(string $table, int $configId): array
    {
        $statement = $this->database->configuration->prepare(
            'SELECT ' . implode(', ', self::COLUMNS) . ' FROM ' . $table
            . ' WHERE config_id = :configId ORDER BY tag, input_output_id'
        );
        $statement->bindValue(':configId', $configId, \PDO::PARAM_INT);
        $statement->execute();
        /** @var list<array<string, int|string|null>> $rows */
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(
            static fn (array $row): array => array_map(
                static fn (int|string|null $value): ?string => $value === null ? null : (string) $value,
                $row
            ),
            $rows
        );
    }

    /**
     * Reads an infile as LOAD DATA does with the manifest clauses: fields terminated by "~~~" and enclosed by
     * double quotes, lines terminated by "######", backslash as escape character, unenclosed \N as NULL.
     * Strict on purpose: any character sequence the writer must not produce fails the test.
     *
     * @return list<array<string, string|null>>
     */
    private function readInfile(string $file): array
    {
        $content = (string) file_get_contents($file);
        $escapes = ['0' => "\0", 'b' => "\x08", 'n' => "\n", 'r' => "\r", 't' => "\t", 'Z' => "\x1A"];
        $rows = [];
        $fields = [];
        $position = 0;
        $length = strlen($content);
        while ($position < $length) {
            if (substr($content, $position, 2) === '\\N') {
                $fields[] = null;
                $position += 2;
            } else {
                self::assertSame('"', $content[$position], 'Fields must be enclosed at offset ' . $position);
                $value = '';
                $position++;
                while (true) {
                    self::assertLessThan($length, $position, 'Unterminated field');
                    $character = $content[$position];
                    if ($character === '\\') {
                        $escaped = $content[$position + 1];
                        $value .= $escapes[$escaped] ?? $escaped;
                        $position += 2;
                    } elseif ($character === '"') {
                        $position++;

                        break;
                    } else {
                        $value .= $character;
                        $position++;
                    }
                }
                $fields[] = $value;
            }

            if (substr($content, $position, 3) === '~~~') {
                $position += 3;
            } else {
                self::assertSame('######', substr($content, $position, 6), 'Line separator expected at ' . $position);
                $position += 6;
                self::assertCount(count(self::COLUMNS), $fields);
                /** @var array<string, string|null> $row */
                $row = array_combine(self::COLUMNS, $fields);
                $rows[] = $row;
                $fields = [];
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>> parameters of the exported inputs/outputs, in the import order
     */
    private function expectedParameters(): array
    {
        return [
            self::TRICKY_VALUES,
            [],
            ['filters_category' => ['neb', "bbdo\t\"quoted\""]],
            [
                'path' => '/usr/share/centreon-broker/lua/stream "connector".lua',
                'lua_parameter' => [
                    ['name' => 'json', 'value' => '{"a": "b\\"c", "list": [], "object": {}}', 'type' => 'string'],
                    ['name' => 'multiline', 'value' => "first\n\"second\"\\\n\tthird ######", 'type' => 'string'],
                ],
            ],
        ];
    }
}
