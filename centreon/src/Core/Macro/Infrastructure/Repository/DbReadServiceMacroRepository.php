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
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;

/**
 * @phpstan-type _Macro array{
 *    svc_svc_id:int,
 *    svc_macro_name:string,
 *    svc_macro_value:string,
 *    is_password:int|null,
 *    description:string|null,
 *    macro_order:int
 * }
 */
class DbReadServiceMacroRepository extends AbstractRepositoryRDB implements ReadServiceMacroRepositoryInterface
{
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
    public function findByServiceIds(int ...$serviceIds): array
    {
        if ($serviceIds === []) {
            return [];
        }

        $bindValues = [];
        foreach ($serviceIds as $index => $serviceId) {
            $bindValues[':service_id' . $index] = $serviceId;
        }
        $serviceIdsAsString = implode(',', array_keys($bindValues));

        $statement = $this->db->prepare($this->translateDbName(
            <<<SQL
                SELECT
                    m.svc_macro_name,
                    m.svc_macro_value,
                    m.is_password,
                    m.svc_svc_id,
                    m.description,
                    m.macro_order
                FROM `:db`.on_demand_macro_service m
                WHERE m.svc_svc_id IN ({$serviceIdsAsString})
                SQL
        ));
        foreach ($bindValues as $index => $serviceId) {
            $statement->bindValue($index, $serviceId, \PDO::PARAM_INT);
        }
        $statement->execute();

        $macros = [];
        $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($results as $result) {
            /** @var _Macro $result */
            $macros[] = $this->createMacro($result);
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
                        m.svc_macro_name,
                        m.svc_macro_value,
                        m.is_password,
                        m.svc_svc_id,
                        m.description,
                        m.macro_order
                FROM `:db`.on_demand_macro_service m
                WHERE m.is_password = 1
                SQL
        ));
        $statement->execute();

        $macros = [];
        $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($results as $result) {
            /** @var _Macro $result */
            $macros[] = $this->createMacro($result);
        }

        return $macros;
    }

    /**
     * @param _Macro $data
     *
     * @throws AssertionFailedException
     *
     * @return Macro
     */
    private function createMacro(array $data): Macro
    {
        preg_match('/^\$_SERVICE(?<macro_name>.*)\$$/', $data['svc_macro_name'], $matches);

        $macro = new Macro(
            (int) $data['svc_svc_id'],
            $matches['macro_name'],
            $data['svc_macro_value'],
        );
        $macro->setIsPassword($data['is_password'] === 1);
        $macro->setDescription($data['description'] ?? '');
        $macro->setOrder((int) $data['macro_order']);

        return $macro;
    }
}
