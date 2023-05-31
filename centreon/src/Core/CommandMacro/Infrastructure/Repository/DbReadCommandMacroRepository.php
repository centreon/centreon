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

namespace Core\CommandMacro\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;

class DbReadCommandMacroRepository extends AbstractRepositoryRDB implements ReadCommandMacroRepositoryInterface
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
    public function findByCommandIdAndType(int $commandId, CommandMacroType $type): array
    {
        $this->info('Get command macros by command id and type',['command_id' => $commandId, 'type' => $type]);

        $statement = $this->db->prepare($this->translateDbName(
            <<<'SQL'
                SELECT
                    m.command_command_id,
                    m.command_macro_name,
                    m.command_macro_desciption,
                    m.command_macro_type
                FROM `:db`.on_demand_macro_command m
                WHERE
                    m.command_command_id = :commandId
                    AND m.command_macro_type = :macroType
                SQL
        ));
        $statement->bindValue(':macroType', CommandMacroType::Host->value, \PDO::PARAM_STR);
        $statement->bindValue(':commandId', $commandId, \PDO::PARAM_INT);
        $statement->execute();

        $macros = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $result) {
            /** @var array{
             *    command_command_id:int,
             *    command_macro_name:string,
             *    command_macro_desciption:string,
             *    command_macro_type:string
             * } $result */
            $macros[] = $this->createCommandMacroFromArray($result);
        }

        return $macros;
    }

    /**
     * @param array{
     *    command_command_id:int,
     *    command_macro_name:string,
     *    command_macro_desciption:string,
     *    command_macro_type: string
     * } $data
     *
     * @return CommandMacro
     */
    private function createCommandMacroFromArray(array $data): CommandMacro
    {
        $macro = new CommandMacro(
            (int) $data['command_command_id'],
            CommandMacroType::from($data['command_macro_type']),
            $data['command_macro_name'],
        );
        $macro->setDescription($data['command_macro_desciption']);

        return $macro;
    }
}

