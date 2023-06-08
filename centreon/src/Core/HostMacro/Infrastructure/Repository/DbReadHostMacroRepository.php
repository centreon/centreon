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

namespace Core\HostMacro\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\HostMacro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\HostMacro\Domain\Model\HostMacro;
use Utility\SqlConcatenator;

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

        $concatenator = new SqlConcatenator();
        $concatenator
            ->defineSelect(
                <<<'SQL'
                    SELECT
                        m.host_macro_name,
                        m.host_macro_value,
                        m.is_password,
                        m.host_host_id,
                        m.description,
                        m.macro_order
                    FROM `:db`.on_demand_macro_host m
                    SQL
            )
            ->appendWhere('WHERE m.host_host_id IN (:host_ids)')
            ->storeBindValueMultiple(':host_ids', $hostIds, \PDO::PARAM_INT);

        $statement = $this->db->prepare($this->translateDbName($concatenator->__toString()));
        $concatenator->bindValuesToStatement($statement);
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
     * @return HostMacro
     */
    private function createHostMacroFromArray(array $data): HostMacro
    {
        preg_match('/^\$_HOST(?<macro_name>.*)\$$/', $data['host_macro_name'], $matches);

        $macro = new HostMacro(
            (int) $data['host_host_id'],
            $matches['macro_name'],
            $data['host_macro_value'],
        );
        $macro->setIsPassword((bool) $data['is_password']);
        $macro->setDescription($data['description'] ?? '');
        $macro->setOrder($data['macro_order']);

        return $macro;
    }
}
