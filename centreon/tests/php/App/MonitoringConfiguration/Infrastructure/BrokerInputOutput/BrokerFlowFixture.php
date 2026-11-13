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

use Core\Broker\Domain\Model\NewBrokerInputOutput;
use Core\Broker\Infrastructure\Repository\DbReadBrokerInputOutputRepository;
use Core\Broker\Infrastructure\Repository\BrokerInfoRowsConverter;
use Core\Broker\Infrastructure\Repository\DbWriteBrokerInputOutputRepository;

/**
 * Seeds broker inputs/outputs for the characterization tests.
 *
 * - addThroughApi() goes through the production repositories used by the broker API and the legacy form,
 *   so it follows the storage format automatically.
 * - insertLegacyRows() is the ONLY place where tests write the storage directly. It reproduces what writers
 *   that bypass the API (CLAPI, remote wizard, base configuration, modules, upgrade scripts) store today,
 *   expressed in the former cfg_centreonbroker_info EAV encoding. The rows are converted with the production
 *   converter used by the upgrade, exactly as on an upgraded platform.
 *
 * @phpstan-import-type _BrokerInputOutputParameter from \Core\Broker\Domain\Model\BrokerInputOutput
 *
 * @phpstan-type _LegacyRow array{
 *     key: string,
 *     value: string,
 *     grp_level?: int,
 *     subgrp_id?: int|null,
 *     parent_grp_id?: int|null,
 *     fieldIndex?: int|null,
 * }
 */
final readonly class BrokerFlowFixture
{
    private DbReadBrokerInputOutputRepository $readRepository;

    private DbWriteBrokerInputOutputRepository $writeRepository;

    public function __construct(private BrokerTestDatabase $database)
    {
        $this->readRepository = new DbReadBrokerInputOutputRepository($database->connection);
        $this->writeRepository = new DbWriteBrokerInputOutputRepository($database->connection);
    }

    /**
     * Adds an input/output exactly as the broker API (and therefore the legacy form) does, without the vault.
     *
     * @param 'input'|'output' $tag
     * @param array<string, mixed> $parameters the request "parameters" payload
     *
     * @return int the input/output id
     */
    public function addThroughApi(int $configId, string $tag, int $typeId, string $name, array $parameters): int
    {
        $type = $this->readRepository->findType($tag, $typeId)
            ?? throw new \LogicException(sprintf('Unknown broker %s type %d', $tag, $typeId));
        $fields = $this->readRepository->findParametersByType($typeId);

        // The API passes the validated request payload as is, null values included.
        /** @var array<string, _BrokerInputOutputParameter> $parameters */
        return $this->writeRepository->add(new NewBrokerInputOutput($tag, $type, $name, $parameters), $configId, $fields);
    }

    /**
     * Stores an input/output the way writers bypassing the API do, rows being inserted in the given order.
     *
     * @param string $group 'input', 'output' or the legacy 'logger'
     * @param list<_LegacyRow> $rows
     */
    public function insertLegacyRows(int $configId, string $group, ?int $groupId, array $rows): void
    {
        $legacyRows = array_map(
            static fn (array $row): array => [
                'config_group' => $group,
                'config_group_id' => $groupId,
                'config_key' => $row['key'],
                'config_value' => $row['value'],
                'subgrp_id' => $row['subgrp_id'] ?? null,
                'fieldIndex' => $row['fieldIndex'] ?? null,
            ],
            $rows
        );

        $statement = $this->database->configuration->prepare(
            <<<'SQL'
                INSERT INTO cfg_centreonbroker_input_output (
                    config_id, tag, input_output_id, name, type_id, type, parameters
                ) VALUES (
                    :configId, :tag, :inputOutputId, :name, :typeId, :type, :parameters
                )
                SQL
        );
        foreach (BrokerInfoRowsConverter::convert($legacyRows) as $inputOutput) {
            $statement->bindValue(':configId', $configId, \PDO::PARAM_INT);
            $statement->bindValue(':tag', $inputOutput->tag);
            $statement->bindValue(':inputOutputId', $inputOutput->id, \PDO::PARAM_INT);
            $statement->bindValue(':name', $inputOutput->name);
            $this->bindNullableInt($statement, ':typeId', $inputOutput->typeId);
            $statement->bindValue(':type', $inputOutput->type, $inputOutput->type === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
            $statement->bindValue(':parameters', $inputOutput->encodeParameters());
            $statement->execute();
        }
    }

    /**
     * Shortcut for flat rows: ['key' => 'value', ...] in insertion order.
     *
     * @param array<string, string> $values
     *
     * @return list<_LegacyRow>
     */
    public static function flatRows(array $values): array
    {
        $rows = [];
        foreach ($values as $key => $value) {
            $rows[] = ['key' => $key, 'value' => $value];
        }

        return $rows;
    }

    private function bindNullableInt(\PDOStatement $statement, string $name, ?int $value): void
    {
        $statement->bindValue($name, $value, $value === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
    }
}
