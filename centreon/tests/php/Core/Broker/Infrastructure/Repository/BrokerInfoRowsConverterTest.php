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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BrokerInfoRowsConverterTest extends TestCase
{
    #[Test]
    public function itConvertsAFlowWrittenByTheApi(): void
    {
        $rows = [
            $this->row('output', 1, 'type', 'lua'),
            $this->row('output', 1, 'name', 'my-lua'),
            $this->row('output', 1, 'blockId', '1_33'),
            $this->row('output', 1, 'path', '/usr/share/lua/script.lua'),
            $this->row('output', 1, 'filters', '', subgrpId: 1),
            $this->row('output', 1, 'category', 'neb', grpLevel: 1, parentGrpId: 1),
            $this->row('output', 1, 'category', 'bam', grpLevel: 1, parentGrpId: 1),
            $this->row('output', 1, 'lua_parameter__type', 'string', fieldIndex: 0),
            $this->row('output', 1, 'lua_parameter__name', 'first', fieldIndex: 0),
            $this->row('output', 1, 'lua_parameter__value', 'one', fieldIndex: 0),
            $this->row('output', 1, 'lua_parameter__type', 'password', fieldIndex: 1),
            $this->row('output', 1, 'lua_parameter__name', 'second', fieldIndex: 1),
            $this->row('output', 1, 'lua_parameter__value', 'secret::path::key', fieldIndex: 1),
        ];

        self::assertEquals(
            [
                new StoredBrokerInputOutput('output', 1, 'my-lua', 'lua', 33, [
                    'path' => '/usr/share/lua/script.lua',
                    'filters_category' => ['neb', 'bam'],
                    'lua_parameter' => [
                        ['type' => 'string', 'name' => 'first', 'value' => 'one'],
                        ['type' => 'password', 'name' => 'second', 'value' => 'secret::path::key'],
                    ],
                ]),
            ],
            BrokerInfoRowsConverter::convert($rows)
        );
    }

    #[Test]
    public function itConvertsFiltersWrittenWithoutParentRowLinks(): void
    {
        // Remote server wizard and BAM: "filters" parent row and "category" rows at level 0 without links.
        $rows = [
            $this->row('output', 1, 'name', 'forward-to-master'),
            $this->row('output', 1, 'filters', ''),
            $this->row('output', 1, 'category', 'neb'),
            $this->row('output', 1, 'category', 'correlation', grpLevel: 1),
            $this->row('output', 1, 'type', 'ipv4'),
            $this->row('output', 1, 'blockId', '1_3'),
        ];

        self::assertEquals(
            [new StoredBrokerInputOutput('output', 1, 'forward-to-master', 'ipv4', 3, [
                'filters_category' => ['neb', 'correlation'],
            ])],
            BrokerInfoRowsConverter::convert($rows)
        );
    }

    #[Test]
    public function itConvertsFiltersWrittenByClapi(): void
    {
        // CLAPI links children to the field group id and adds a parent row on every setparam.
        $rows = [
            $this->row('output', 0, 'name', 'event-script'),
            $this->row('output', 0, 'type', 'event_script'),
            $this->row('output', 0, 'blockId', '1_38'),
            $this->row('output', 0, 'filters', '', subgrpId: 1),
            $this->row('output', 0, 'event', 'host_status', grpLevel: 1, parentGrpId: 1),
            $this->row('output', 0, 'filters', '', subgrpId: 1),
            $this->row('output', 0, 'category', 'neb', grpLevel: 1, parentGrpId: 1),
            $this->row('output', 0, 'event', 'service_status', grpLevel: 1, parentGrpId: 1),
        ];

        self::assertEquals(
            [new StoredBrokerInputOutput('output', 0, 'event-script', 'event_script', 38, [
                'filters_event' => ['host_status', 'service_status'],
                'filters_category' => ['neb'],
            ])],
            BrokerInfoRowsConverter::convert($rows)
        );
    }

    #[Test]
    public function itKeepsTheLastValueOfARepeatedKey(): void
    {
        // CLAPI setparam splits values on commas into several rows of the same key.
        $rows = [
            $this->row('input', 1, 'name', 'input'),
            $this->row('input', 1, 'host', 'first'),
            $this->row('input', 1, 'port', '5669'),
            $this->row('input', 1, 'host', 'second'),
        ];

        self::assertSame(
            ['host' => 'second', 'port' => '5669'],
            BrokerInfoRowsConverter::convert($rows)[0]->parameters
        );
    }

    #[Test]
    public function itKeepsEmptyValues(): void
    {
        $rows = [
            $this->row('output', 1, 'name', 'sql'),
            $this->row('output', 1, 'db_password', ''),
            $this->row('output', 1, 'retention', ' '),
        ];

        self::assertSame(
            ['db_password' => '', 'retention' => ' '],
            BrokerInfoRowsConverter::convert($rows)[0]->parameters
        );
    }

    #[Test]
    public function itKeepsAFiltersRowHoldingAValueAsASimpleParameter(): void
    {
        $rows = [
            $this->row('output', 1, 'name', 'output'),
            $this->row('output', 1, 'filters', 'unexpected'),
        ];

        self::assertSame(['filters' => 'unexpected'], BrokerInfoRowsConverter::convert($rows)[0]->parameters);
    }

    #[Test]
    public function itOrdersGroupedFieldsByFirstAppearanceOfTheirIndex(): void
    {
        $rows = [
            $this->row('output', 1, 'name', 'influx'),
            $this->row('output', 1, 'metrics_column__name', 'third', fieldIndex: 7),
            $this->row('output', 1, 'status_column__name', 'status', fieldIndex: 0),
            $this->row('output', 1, 'metrics_column__name', 'first', fieldIndex: 2),
            $this->row('output', 1, 'metrics_column__value', 'v3', fieldIndex: 7),
            $this->row('output', 1, 'metrics_column__value', 'v1', fieldIndex: 2),
            $this->row('output', 1, 'metrics_column__value', 'overridden', fieldIndex: 2),
        ];

        self::assertSame(
            [
                'metrics_column' => [
                    ['name' => 'third', 'value' => 'v3'],
                    ['name' => 'first', 'value' => 'overridden'],
                ],
                'status_column' => [['name' => 'status']],
            ],
            BrokerInfoRowsConverter::convert($rows)[0]->parameters
        );
    }

    #[Test]
    public function itSplitsGroupedFieldKeysLikeTheConfigurationGeneration(): void
    {
        $rows = [
            $this->row('output', 1, 'name', 'lua'),
            $this->row('output', 1, 'lua_parameter__value__extra', 'kept under value', fieldIndex: 0),
            $this->row('output', 1, 'orphan', 'kept under an empty name', fieldIndex: 1),
        ];

        self::assertSame(
            [
                'lua_parameter' => [['value' => 'kept under value']],
                'orphan' => [['' => 'kept under an empty name']],
            ],
            BrokerInfoRowsConverter::convert($rows)[0]->parameters
        );
    }

    #[Test]
    public function itSeparatesFlowsByTagAndIdInReadOrder(): void
    {
        $rows = [
            $this->row('input', 0, 'name', 'input-zero'),
            $this->row('output', 2, 'name', 'output-two'),
            $this->row('output', 1, 'name', 'output-one'),
            $this->row('input', 0, 'port', '5669'),
            $this->row('output', 2, 'port', '5670'),
        ];

        $flows = BrokerInfoRowsConverter::convert($rows);

        self::assertSame(
            [['input', 0, 'input-zero', ['port' => '5669']], ['output', 2, 'output-two', ['port' => '5670']], ['output', 1, 'output-one', []]],
            array_map(
                static fn (StoredBrokerInputOutput $flow): array => [$flow->tag, $flow->id, $flow->name, $flow->parameters],
                $flows
            )
        );
    }

    #[Test]
    public function itDropsLegacyLoggerAndUnknownGroups(): void
    {
        $rows = [
            $this->row('logger', 0, 'name', '/var/log/centreon-broker/central.log'),
            $this->row('logger', 0, 'type', 'file'),
            $this->row('unknown', 0, 'name', 'unknown'),
            $this->row('output', 0, 'name', 'output'),
        ];

        $flows = BrokerInfoRowsConverter::convert($rows);

        self::assertCount(1, $flows);
        self::assertSame('output', $flows[0]->tag);
    }

    #[Test]
    public function itToleratesMissingMetaRows(): void
    {
        $rows = [$this->row('output', 3, 'port', '5669')];

        self::assertEquals(
            [new StoredBrokerInputOutput('output', 3, '', null, null, ['port' => '5669'])],
            BrokerInfoRowsConverter::convert($rows)
        );
    }

    #[Test]
    public function itReadsTheTypeIdFromTheBlockIdOfInputsAndOutputs(): void
    {
        $flows = BrokerInfoRowsConverter::convert([
            $this->row('input', 1, 'blockId', '2_3'),
            $this->row('output', 1, 'blockId', '1_14'),
        ]);

        self::assertSame([3, 14], [$flows[0]->typeId, $flows[1]->typeId]);
    }

    #[Test]
    public function itAcceptsRowsFetchedAsStrings(): void
    {
        $flows = BrokerInfoRowsConverter::convert([
            [
                'config_group' => 'output',
                'config_group_id' => '4',
                'config_key' => 'lua_parameter__name',
                'config_value' => 'name',
                'grp_level' => '0',
                'subgrp_id' => null,
                'parent_grp_id' => null,
                'fieldIndex' => '0',
            ],
        ]);

        self::assertSame(4, $flows[0]->id);
        self::assertSame(['lua_parameter' => [['name' => 'name']]], $flows[0]->parameters);
    }

    /**
     * @return array<string, string|int|null>
     */
    private function row(
        string $group,
        int $groupId,
        string $key,
        string $value,
        int $grpLevel = 0,
        ?int $subgrpId = null,
        ?int $parentGrpId = null,
        ?int $fieldIndex = null,
    ): array {
        return [
            'config_group' => $group,
            'config_group_id' => $groupId,
            'config_key' => $key,
            'config_value' => $value,
            'grp_level' => $grpLevel,
            'subgrp_id' => $subgrpId,
            'parent_grp_id' => $parentGrpId,
            'fieldIndex' => $fieldIndex,
        ];
    }
}
