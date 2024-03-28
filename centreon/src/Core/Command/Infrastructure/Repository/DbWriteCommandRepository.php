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

namespace Core\Command\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Command\Application\Repository\WriteCommandRepositoryInterface;
use Core\Command\Domain\Model\NewCommand;
use Core\Command\Infrastructure\Model\CommandTypeConverter;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;

class DbWriteCommandRepository extends AbstractRepositoryRDB implements WriteCommandRepositoryInterface
{
    use LoggerTrait;
    use RepositoryTrait;

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
    public function add(NewCommand $command): int
    {
        $alreadyInTransaction = $this->db->inTransaction();
        if (! $alreadyInTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $commandId = $this->addCommand($command);
            $this->addArguments($commandId, $command);
            $this->addMacros($commandId, $command);

            if (! $alreadyInTransaction) {
                $this->db->commit();
            }

            return $commandId;
        } catch (\Throwable $ex) {
             $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            if (! $alreadyInTransaction) {
                $this->db->rollBack();
            }

            throw $ex;
        }
    }

    /**
     * @param NewCommand $command
     *
     * @throws \Throwable
     *
     * @return int
     */
    private function addCommand(NewCommand $command): int
    {
        $request = $this->translateDbName(
            <<<'SQL'
                INSERT INTO `:db`.command
                (
                    command_name,
                    command_line,
                    command_example,
                    command_type,
                    graph_id,
                    connector_id,
                    enable_shell
                ) VALUES
                (
                    :command_name,
                    :command_line,
                    :argument_example,
                    :command_type,
                    :graph_id,
                    :connector_id,
                    :enable_shell
                )
                SQL
        );
        $statement = $this->db->prepare($request);

        $statement->bindValue(':command_name', $command->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':command_type', CommandTypeConverter::toInt($command->getType()), \PDO::PARAM_STR);
        $statement->bindValue(':command_line', $command->getCommandLine(), \PDO::PARAM_STR);
        $statement->bindValue(':argument_example', $command->getArgumentExample(), \PDO::PARAM_STR);
        $statement->bindValue(':graph_id', $command->getGraphTemplateId(), \PDO::PARAM_INT);
        $statement->bindValue(':connector_id', $command->getConnectorId(), \PDO::PARAM_INT);
        $statement->bindValue(
            ':enable_shell',
            (new BoolToEnumNormalizer())->normalize($command->isShellEnabled()),
            \PDO::PARAM_INT
        );
        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param int $commandId
     * @param NewCommand $command
     *
     * @throws \Throwable
     */
    private function addArguments(int $commandId, NewCommand $command): void
    {
        if ($command->getArguments() === []) {
            $this->debug("No argument for command {$commandId}");

            return;
        }

        $request = <<<'SQL'
            INSERT INTO `:db`.command_arg_description
            (
                cmd_id,
                macro_name,
                macro_description
            ) VALUES
            SQL;

        foreach ($command->getArguments() as $key => $argument) {
            $request .= $key > 0 ? ', ' : '';
            $request .= <<<SQL
                (
                    :commandId,
                    :argName_{$key},
                    :argDescription_{$key}
                )
                SQL;
        }

        $statement = $this->db->prepare($this->translateDbName($request));

        foreach ($command->getArguments() as $key => $argument) {
            $statement->bindValue(":argName_{$key}", $argument->getName(), \PDO::PARAM_STR);
            $statement->bindValue(":argDescription_{$key}", $argument->getDescription(), \PDO::PARAM_STR);
        }
        $statement->bindValue(':commandId', $commandId, \PDO::PARAM_STR);
        $statement->execute();
    }

    /**
     * @param int $commandId
     * @param NewCommand $command
     *
     * @throws \Throwable
     */
    private function addMacros(int $commandId, NewCommand $command): void
    {
        if ($command->getMacros() === []) {
            $this->debug("No macro for command {$commandId}");

            return;
        }

        $request = <<<'SQL'
            INSERT INTO `:db`.on_demand_macro_command
            (
                command_macro_name,
                command_macro_desciption,
                command_command_id,
                command_macro_type
            ) VALUES
            SQL;

        foreach ($command->getMacros() as $key => $macro) {
            $request .= $key > 0 ? ', ' : '';
            $request .= <<<SQL
                (
                    :macroName_{$key},
                    :macroDescription_{$key},
                    :commandId,
                    :macroType_{$key}
                )
                SQL;
        }

        $statement = $this->db->prepare($this->translateDbName($request));

        foreach ($command->getMacros() as $key => $macro) {
            $statement->bindValue(":macroName_{$key}", $macro->getName(), \PDO::PARAM_STR);
            $statement->bindValue(":macroDescription_{$key}", $macro->getDescription(), \PDO::PARAM_STR);
            $statement->bindValue(":macroType_{$key}", $macro->getType()->value, \PDO::PARAM_STR);
        }
        $statement->bindValue(':commandId', $commandId, \PDO::PARAM_STR);
        $statement->execute();
    }
}
