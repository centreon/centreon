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

namespace Core\Command\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Command\Application\Repository\WriteCommandRepositoryInterface;
use Core\Command\Domain\Model\NewCommand;
use Core\Command\Infrastructure\Model\CommandTypeConverter;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;
use Core\Common\Infrastructure\Repository\RepositoryTrait;
use Core\Common\Infrastructure\RequestParameters\Normalizer\BoolToEnumNormalizer;
use Core\Command\Domain\Model\Command;
use Core\Command\Domain\Model\Argument;
use Utility\Difference\BasicDifference;
use Core\CommandMacro\Domain\Model\CommandMacro;

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

    /**
     * 
     * @param int $commandId
     * @throws \Throwable
     * @return void
     */
    public function delete(int $commandId): void {
         $alreadyInTransaction = $this->db->inTransaction();
        if (! $alreadyInTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $this->deleteCommand($commandId);
            $this->deleteArguments($commandId);
            $this->deleteMacros($commandId);

            if (! $alreadyInTransaction) {
                $this->db->commit();
            }

            return;
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            if (! $alreadyInTransaction) {
                $this->db->rollBack();
            }

            throw $ex;
        }
    }

    /**
     * @param int $commandId
     *
     * @throws \Throwable
     *
     * @return int
     */
    private function deleteCommand(int $commandId): void
    {
        $this->info('Delete command', ['id' => $commandId]);

        $query = <<<'SQL'
            DELETE FROM `:db`.`command`
            WHERE command_id = :command_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':command_id', $commandId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @param int $commandId
     *
     * @throws \Throwable
     *
     * @return int
     */
    private function deleteArguments(int $commandId): void
    {
        $this->info('Delete arguments with command', ['id' => $commandId]);

        $query = <<<'SQL'
            DELETE FROM `:db`.`command_arg_description`
            WHERE cmd_id = :command_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':command_id', $commandId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @param int $commandId
     *
     * @throws \Throwable
     *
     * @return int
     */
    private function deleteMacros(int $commandId): void
    {
        $this->info('Delete macros with command', ['id' => $commandId]);

        $query = <<<'SQL'
            DELETE FROM `:db`.`on_demand_macro_command`
            WHERE command_command_id = :command_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':command_id', $commandId, \PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @inheritDoc
     */
    public function update(Command $originalCommand, Command $updatedCommand): void
    {
        $alreadyInTransaction = $this->db->inTransaction();
        if (! $alreadyInTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $this->updateCommand($updatedCommand);
            $this->updateArguments($originalCommand, $updatedCommand);
            $this->updateMacros($originalCommand, $updatedCommand);

            if (! $alreadyInTransaction) {
                $this->db->commit();
            }

            return;
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            if (! $alreadyInTransaction) {
                $this->db->rollBack();
            }

            throw $ex;
        }
    }

    /**
     * @param Command $command
     *
     * @throws \Throwable
     *
     * @return int
     */
    private function updateCommand(Command $command): void
    {
        $request = $this->translateDbName(
            <<<'SQL'
                UPDATE `:db`.command
                SET
                    command_name = :command_name,
                    command_line = :command_line,
                    command_example = :argument_example,
                    command_type = :command_type,
                    graph_id = :graph_id,
                    connector_id = :connector_id,
                    enable_shell = :enable_shell
                WHERE command_id = :command_id
                SQL
        );
        $statement = $this->db->prepare($request);

        if($command->getGraphTemplate() !== null) {
            $graphId = $command->getGraphTemplate()->getId();
        } else {
            $graphId = null;
        }
        if($command->getConnector() !== null) {
            $connectorId = $command->getConnector()->getId();
        } else {
            $connectorId = null;
        }

        $statement->bindValue(':command_id', $command->getId(), \PDO::PARAM_INT);
        $statement->bindValue(':command_name', $command->getName(), \PDO::PARAM_STR);
        $statement->bindValue(':command_type', CommandTypeConverter::toInt($command->getType()), \PDO::PARAM_STR);
        $statement->bindValue(':command_line', $command->getCommandLine(), \PDO::PARAM_STR);
        $statement->bindValue(':argument_example', $command->getArgumentExample(), \PDO::PARAM_STR);
        $statement->bindValue(':graph_id', $graphId, \PDO::PARAM_INT);
        $statement->bindValue(':connector_id', $connectorId, \PDO::PARAM_INT);
        $statement->bindValue(
            ':enable_shell',
            (new BoolToEnumNormalizer())->normalize($command->isShellEnabled()),
            \PDO::PARAM_INT
        );
        $statement->execute();

        return;
    }

    /**
     * @param Command $command
     *
     * @throws \Throwable
     */
    private function updateArguments(Command $originalCommand, Command $updatedCommand): void
    {
        $originalArgumentsNames = array_map(
            static fn (Argument $argument): string => $argument->getName(),
            $originalCommand->getArguments()
        );
        $updatedArgumentsNames = array_map(
            static fn (Argument $argument): string => $argument->getName(),
            $updatedCommand->getArguments()
        );

        $categoryDiff = new BasicDifference($originalArgumentsNames, $updatedArgumentsNames);
        $addedArguments = $categoryDiff->getAdded();
        $removedArguments = $categoryDiff->getRemoved();
        $updatedArguments = $categoryDiff->getCommon();

        // Update the modified arguments
        foreach ($updatedArguments as $updatedArgumentName) {
            foreach ($updatedCommand->getArguments() as $updatedArgument) {
                if ($updatedArgument->getName() === $updatedArgumentName) {
                    $request = $this->translateDbName(
                        <<<'SQL'
                            UPDATE `:db`.command_arg_description
                            SET
                                macro_description = :macro_description
                            WHERE macro_name = :macro_name AND cmd_id = :command_id
                            SQL
                    );
                    $statement = $this->db->prepare($request);
                    $statement->bindValue(':command_id', $originalCommand->getId(), \PDO::PARAM_INT);
                    $statement->bindValue(':macro_name', $updatedArgument->getName(), \PDO::PARAM_STR);
                    $statement->bindValue(':macro_description', $updatedArgument->getDescription(), \PDO::PARAM_STR);
                    $statement->execute();
                }
            }
        }

        // Delete the removed arguments
        if (! empty($removedArguments)) {
            $query = <<<'SQL'
            DELETE FROM `:db`.command_arg_description
            WHERE cmd_id = :command_id AND macro_name IN (:argument_names)
            SQL;

            $statement = $this->db->prepare($this->translateDbName($query));
            $statement->bindValue(':command_id', $originalCommand->getId(), \PDO::PARAM_INT);
            $statement->bindValue('argument_names', implode(", ", $removedArguments), \PDO::PARAM_STR);
            $statement->execute();
        }

        // Add the new arguments (batch insert)
        if (! empty($addedArguments)) {
            $request = <<<'SQL'
            INSERT INTO `:db`.command_arg_description
            (
                cmd_id,
                macro_name,
                macro_description
            ) VALUES
            SQL;
            foreach ($updatedCommand->getArguments() as $key => $arg) {
                if (in_array($arg->getName(), $addedArguments, true)) {
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
            }
            foreach ($updatedCommand->getArguments() as $key => $arg) {
                if (in_array($arg->getName(), $addedArguments, true)) {
                    $statement->bindValue(":argName_{$key}", $arg->getName(), \PDO::PARAM_STR);
                    $statement->bindValue(":argDescription_{$key}", $arg->getDescription(), \PDO::PARAM_STR);
                }
            }

            $statement->bindValue(':commandId', $originalCommand->getId(), \PDO::PARAM_STR);
            $statement->execute();
        }
    }

    /**
     * @param Command $originalCommand
     * @param Command $updatedCommand
     *
     * @throws \Throwable
     */
    private function updateMacros(Command $originalCommand, Command $updatedCommand): void
    {
        $originalMacrosNames = array_map(
            static fn (CommandMacro $macro): string => $macro->getName(),
            $originalCommand->getMacros()
        );
        $updatedMacrosNames = array_map(
            static fn (CommandMacro $macro): string => $macro->getName(),
            $updatedCommand->getMacros()
        );

        $categoryDiff = new BasicDifference($originalMacrosNames, $updatedMacrosNames);
        $addedMacros = $categoryDiff->getAdded();
        $removedMacros = $categoryDiff->getRemoved();
        $updatedMacros = $categoryDiff->getCommon();

        // Update modified macros (individual updates)
        foreach ($updatedMacros as $updatedMacroName) {
            foreach ($updatedCommand->getMacros() as $updatedMacro) {
                if ($updatedMacro->getName() === $updatedMacroName) {
                    $request = $this->translateDbName(
                        <<<'SQL'
                            UPDATE `:db`.on_demand_macro_command
                            SET
                                command_macro_desciption = :macro_description,
                                command_macro_type = :macro_type
                            WHERE command_macro_name = :macro_name AND command_command_id = :command_id
                            SQL
                    );
                    $statement = $this->db->prepare($request);

                    $statement->bindValue(':command_id', $originalCommand->getId(), \PDO::PARAM_INT);
                    $statement->bindValue(':macro_name', $updatedMacro->getName(), \PDO::PARAM_STR);
                    $statement->bindValue(':macro_description', $updatedMacro->getDescription(), \PDO::PARAM_STR);
                    $statement->bindValue(':macro_type', $updatedMacro->getType()->value, \PDO::PARAM_STR);
                    $statement->execute();
                }
            }
        }

        // Delete removed macros
        if (! empty($removedMacros)) {
            $query = <<<'SQL'
            DELETE FROM `:db`.on_demand_macro_command
            WHERE command_command_id = :command_id AND command_macro_name IN (:macro_names)
            SQL;

            $statement = $this->db->prepare($this->translateDbName($query));
            $statement->bindValue(':command_id', $originalCommand->getId(), \PDO::PARAM_INT);
            $statement->bindValue('macro_names', implode(", ", $removedMacros), \PDO::PARAM_STR);
            $statement->execute();
        }

        // Add new macros (batch insert)
        if (! empty($addedMacros)) {
            $request = <<<'SQL'
                INSERT INTO `:db`.on_demand_macro_command
                (
                    command_macro_name,
                    command_macro_desciption,
                    command_command_id,
                    command_macro_type
                ) VALUES
            SQL;
            foreach ($updatedCommand->getMacros() as $key => $macro) {
                if (in_array($macro->getName(), $addedMacros, true)) {
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
            }
            foreach ($updatedCommand->getMacros() as $key => $macro) {
                if (in_array($macro->getName(), $addedMacros, true)) {
                    $statement->bindValue(":macroName_{$key}", $macro->getName(), \PDO::PARAM_STR);
                    $statement->bindValue(":macroDescription_{$key}", $macro->getDescription(), \PDO::PARAM_STR);
                    $statement->bindValue(":macroType_{$key}", $macro->getType(), \PDO::PARAM_INT);
                }
            }

            $statement->bindValue(':commandId', $originalCommand->getId(), \PDO::PARAM_STR);
            $statement->execute();
        }
    }
}
