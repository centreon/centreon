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

namespace Tests\Core\Broker\Infrastructure\Repository;

use Core\Broker\Infrastructure\Repository\BrokerInfoRowsConverter;
use Core\Broker\Infrastructure\Repository\StoredBrokerInputOutput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StoredBrokerInputOutputTest extends TestCase
{
    #[Test]
    public function itDecodesARecord(): void
    {
        $inputOutput = StoredBrokerInputOutput::fromRecord([
            'config_id' => '1',
            'tag' => 'input',
            'input_output_id' => '2',
            'name' => 'input',
            'type_id' => '3',
            'type' => 'ipv4',
            'parameters' => '{"port": "5669", "filters_category": ["neb"]}',
        ]);

        self::assertEquals(
            new StoredBrokerInputOutput('input', 2, 'input', 'ipv4', 3, ['port' => '5669', 'filters_category' => ['neb']]),
            $inputOutput
        );
    }

    #[Test]
    public function itDecodesARecordWithoutType(): void
    {
        $inputOutput = StoredBrokerInputOutput::fromRecord([
            'tag' => 'output',
            'input_output_id' => 0,
            'name' => 'output',
            'type_id' => null,
            'type' => null,
            'parameters' => '{}',
        ]);

        self::assertNull($inputOutput->typeId);
        self::assertSame([], $inputOutput->parameters);
    }

    #[Test]
    public function itExpandsIntoLegacyRowsInTheApiOrder(): void
    {
        $inputOutput = new StoredBrokerInputOutput('output', 4, 'lua', 'lua', 33, [
            'lua_parameter' => [['type' => 'string', 'name' => 'a', 'value' => '1']],
            'filters_category' => ['neb', 'bam'],
            'path' => '/script.lua',
        ]);

        self::assertSame(
            [
                ['type', 'lua', 0, null, null, null],
                ['name', 'lua', 0, null, null, null],
                ['blockId', '1_33', 0, null, null, null],
                ['path', '/script.lua', 0, null, null, null],
                ['filters', '', 0, 1, null, null],
                ['category', 'neb', 1, null, 1, null],
                ['category', 'bam', 1, null, 1, null],
                ['lua_parameter__type', 'string', 0, null, null, 0],
                ['lua_parameter__name', 'a', 0, null, null, 0],
                ['lua_parameter__value', '1', 0, null, null, 0],
            ],
            array_map(
                static fn (array $row): array => [
                    $row['config_key'], $row['config_value'], $row['grp_level'],
                    $row['subgrp_id'], $row['parent_grp_id'], $row['fieldIndex'],
                ],
                $inputOutput->toLegacyRows()
            )
        );
        self::assertSame(
            [['output', 4]],
            array_values(array_unique(array_map(
                static fn (array $row): array => [$row['config_group'], $row['config_group_id']],
                $inputOutput->toLegacyRows()
            ), SORT_REGULAR))
        );
    }

    #[Test]
    public function itUsesTheInputPrefixForTheBlockIdOfInputs(): void
    {
        $rows = (new StoredBrokerInputOutput('input', 0, 'input', 'ipv4', 3, []))->toLegacyRows();

        self::assertSame('2_3', $rows[2]['config_value']);
    }

    #[Test]
    public function itOmitsUnknownTypes(): void
    {
        $rows = (new StoredBrokerInputOutput('output', 0, 'output', null, null, ['port' => '5669']))->toLegacyRows();

        self::assertSame(['name', 'port'], array_column($rows, 'config_key'));
    }

    /**
     * @param list<StoredBrokerInputOutput> $inputsOutputs
     */
    #[Test]
    #[DataProvider('provideInputsOutputs')]
    public function itConvertsLegacyRowsBackToTheSameInputOutput(array $inputsOutputs): void
    {
        $rows = array_merge(...array_map(
            static fn (StoredBrokerInputOutput $inputOutput): array => $inputOutput->toLegacyRows(),
            $inputsOutputs
        ));

        self::assertEquals($inputsOutputs, BrokerInfoRowsConverter::convert($rows));
    }

    /**
     * @return iterable<string, array{list<StoredBrokerInputOutput>}>
     */
    public static function provideInputsOutputs(): iterable
    {
        yield 'every kind of parameter' => [[
            new StoredBrokerInputOutput('output', 1, 'influx', 'influxdb', 31, [
                'db_host' => 'localhost',
                'db_password' => '',
                'filters_category' => ['neb', 'storage'],
                'filters_event' => ['neb:host_status'],
                'metrics_column' => [
                    ['name' => 'host', 'value' => '$HOST$', 'type' => 'string', 'is_tag' => 'true'],
                    ['name' => 'metric', 'value' => '$METRIC$', 'type' => 'string', 'is_tag' => 'false'],
                ],
                'status_column' => [['name' => 'state', 'value' => '$STATE$']],
            ]),
        ]];

        yield 'several inputs and outputs' => [[
            new StoredBrokerInputOutput('input', 0, 'input', 'ipv4', 3, ['port' => '5669']),
            new StoredBrokerInputOutput('output', 0, 'sql', 'unified_sql', 34, ['db_name' => 'centreon_storage']),
            new StoredBrokerInputOutput('output', 3, 'no type', null, null, []),
        ]];

        yield 'grouped field without field name' => [[
            new StoredBrokerInputOutput('output', 0, 'lua', 'lua', 33, ['orphan' => [['' => 'value']]]),
        ]];
    }
}
