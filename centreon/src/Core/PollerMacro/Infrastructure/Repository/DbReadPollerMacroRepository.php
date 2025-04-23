<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\PollerMacro\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\PollerMacro\Application\Repository\ReadPollerMacroRepositoryInterface;
use Core\PollerMacro\Domain\Model\PollerMacro;

/**
 * @phpstan-type _Macro array{
 *      resource_id:int,
 *      resource_name:string,
 *      resource_line:string,
 *      resource_comment:string|null,
 *      resource_activate:string,
 *      is_password:int,
 * }
 */
class DbReadPollerMacroRepository extends AbstractRepositoryRDB implements ReadPollerMacroRepositoryInterface
{
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findPasswords(): array
    {
        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                    SELECT * FROM `:db`.`cfg_resource`
                    WHERE `is_password` = 1
                SQL
        ));
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute();

        $macros = [];
        foreach ($statement as $row) {
            /** @var _Macro $row */
            $macros[] = $this->createFromArray($row);
        }

        return $macros;
    }

    /**
     * @param _Macro $data
     *
     * @return PollerMacro
     */
    private function createFromArray(array $data): PollerMacro
    {
        return new PollerMacro(
            id: $data['resource_id'],
            name: $data['resource_name'],
            value: $data['resource_line'],
            comment: $data['resource_comment'],
            isActive: $data['resource_activate'] === '1' ? true : false,
            isPassword: (bool) $data['is_password'],
        );
    }
}
