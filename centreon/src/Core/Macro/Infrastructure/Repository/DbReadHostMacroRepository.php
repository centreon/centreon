<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Macro\Infrastructure\Repository;

use Assert\AssertionFailedException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;

class DbReadHostMacroRepository extends AbstractRepositoryRDB implements ReadHostMacroRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findByHostIds(array $hostIds): array
    {
        $this->info('Get host macros',['host_ids' => $hostIds]);

        if ($hostIds === []) {

            return [];
        }

        $bindValues = [];
        foreach ($hostIds as $index => $hostId) {
            $bindValues[':host_id' . $index] = $hostId;
        }
        $hostIdsAsString = implode(',', array_keys($bindValues));

        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                SELECT
                    m.host_macro_name,
                    m.host_macro_value,
                    m.is_password,
                    m.host_host_id,
                    m.description,
                    m.macro_order
                FROM `:db`.on_demand_macro_host m
                WHERE m.host_host_id IN ({$hostIdsAsString})
                SQL
        ));
        foreach ($bindValues as $token => $hostId) {
            $statement->bindValue($token, $hostId, \PDO::PARAM_INT);
        }
        $statement->execute();

        $macros = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $result) {

            /** @var array{
             *    host_host_id:int,
             *    host_macro_name:string,
             *    host_macro_value:string,
             *    is_password:int|null,
             *    description:string|null,
             *    macro_order:int
             * } $result */
            $macros[] = $this->createHostMacroFromArray($result);
        }

        return $macros;
    }

    /**
     * @inheritDoc
     */
    public function findByHostId(int $hostId): array
    {
        $this->info('Get host macros for a host/host template',['host_id' => $hostId]);

        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT
                    m.host_macro_name,
                    m.host_macro_value,
                    m.is_password,
                    m.host_host_id,
                    m.description,
                    m.macro_order
                FROM `:db`.on_demand_macro_host m
                WHERE m.host_host_id = :host_id
                SQL
        ));
        $statement->bindValue(':host_id', $hostId, \PDO::PARAM_INT);
        $statement->execute();

        $macros = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $result) {

            /** @var array{
             *    host_host_id:int,
             *    host_macro_name:string,
             *    host_macro_value:string,
             *    is_password:int|null,
             *    description:string|null,
             *    macro_order:int
             * } $result */
            $macros[] = $this->createHostMacroFromArray($result);
        }

        return $macros;
    }

    /**
     * @inheritDoc
     */
    public function findPasswords(): array
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT
                    m.host_macro_name,
                    m.host_macro_value,
                    m.is_password,
                    m.host_host_id,
                    m.description,
                    m.macro_order
                FROM `:db`.on_demand_macro_host m
                WHERE m.is_password = 1
                SQL
        ));
        $statement->execute();

        $macros = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $result) {

            /** @var array{
             *    host_host_id:int,
             *    host_macro_name:string,
             *    host_macro_value:string,
             *    is_password:int|null,
             *    description:string|null,
             *    macro_order:int
             * } $result */
            $macros[] = $this->createHostMacroFromArray($result);
        }

        return $macros;
    }

    /**
     * @param array{
     *    host_host_id:int,
     *    host_macro_name:string,
     *    host_macro_value:string,
     *    is_password:int|null,
     *    description:string|null,
     *    macro_order:int
     * } $data
     *
     * @return Macro
     * @throws AssertionFailedException
     */
    private function createHostMacroFromArray(array $data): Macro
    {
        preg_match('/^\$_HOST(?<macro_name>.*)\$$/', $data['host_macro_name'], $matches);

        $macroName = $matches['macro_name'] ?? '';

        $macro = new Macro(
            (int) $data['host_host_id'],
            $macroName,
            $data['host_macro_value'],
        );
        $macro->setIsPassword((bool) $data['is_password']);
        $macro->setDescription($data['description'] ?? '');
        $macro->setOrder($data['macro_order']);

        return $macro;
    }
}
