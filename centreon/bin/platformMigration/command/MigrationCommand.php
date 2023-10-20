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

namespace CloudMigration;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Command to migrate the commands configuration from the source platform to a target platform.
 *
 * @phpstan-type _Argument = array{
 *     name: string,
 *     description: string|null
 * }
 * @phpstan-type _Macro = array{
 *     name: string,
 *     type: integer,
 *     description: string|null
 * }
 * @phpstan-type _Command = array{
 *      command_id: int,
 *      command_name: string,
 *      command_line: string,
 *      command_type: int,
 *      command_example: string|null,
 *      enable_shell: int,
 *      graph_name: string|null,
 *      connector_name: string|null
 * }
 */
class MigrationCommand extends Migration {
    use CommandsInfo;
    use CurlRequestHelper;

    protected static $defaultName = 'migration:commands';

    protected static $defaultDescription = 'Migrate commands configuration data from the current platform to the defined target platform.';

    private string $targetUrl;

    private string $targetToken;

    private \CentreonDB $centreonDB;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<fg=cyan>Migrating commands...</>');

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "Commands configuration migration require some data to be already present on the target platform.\n"
            . "Please make sure all data from requirements are present on the target platform.\n"
            . "(Refer to <fg=green>migration:list commands</> for the list of requirements)\n"
            . 'Do you want to continue ? [Y/n]',
            true
        );

        if (! $helper->ask($input, $output, $question))
        {
            return self::SUCCESS;
        }

        $this->centreonDB = new \CentreonDB();
        $this->targetUrl = $input->getArgument('target-url');
        $this->targetToken = $input->getArgument('target-token');

        try {

            $output->writeln('<fg=cyan>Retrieving data from target platform...</>');

            [$connectors, $graphTemplates] = $this->getTargetRequiredDataOrFail();
            $existingCommands = $this->getTargetCommandsOrFail();

            $output->writeln('<fg=cyan>Retrieving data from source platform...</>');

            [$arguments, $macros] = $this->getSourceArgumentsAndMacrosOrFail();
            $commands = $this->getSourceCommandsOrFail();

        } catch (PlatformMigrationException $ex) {
            $output->writeln('<error>' . $ex->getMessage() . '</error>');
            if ($ex->getPrevious() !== null) {
                $output->writeln('<error>' . $ex->getPrevious()->getMessage() . '</error>', OutputInterface::VERBOSITY_DEBUG);
            }

            return self::FAILURE;
        }

        $output->writeln('<fg=cyan>Creating commands on target platform...</>');

        $success = $skipped = $failed = 0;
        foreach ($commands as $command) {
            if ($this->assertCommandExistOnTargetPlatform($existingCommands, $command)) {
                ++$skipped;
                $output->writeln(
                    '<fg=yellow>'
                    . "[Command][SKIPPED]['{$command['command_name']}']"
                    . " sourceID:{$command['command_id']} => targetID:{$existingCommands[$command['command_name']]}"
                    . '</>',
                );

                continue;
            }

            try {
                $newCommandId = $this->createCommandOrFail(
                    $command,
                    $macros,
                    $arguments,
                    $connectors,
                    $graphTemplates
                );
            } catch (PlatformMigrationException $ex) {
                ++$failed;
                $output->writeln(
                    '<fg=red>'
                    . "[Command][FAILED]['{$command['command_name']}'] sourceID:{$command['command_id']}"
                    . '</>'
                );
                $output->writeln('<fg=red>' . $ex->getMessage() . '</>', OutputInterface::VERBOSITY_DEBUG);

                continue;
            }

            ++$success;
            $output->writeln(
                "[Command][SUCCESS]['{$command['command_name']}']"
                ." sourceID:{$command['command_id']} => targetID:{$newCommandId}"
            );
        }

        $output->writeln("<fg=cyan>Commands migrated (success:{$success}, skipped:{$skipped}, failed:{$failed})</>");

        return self::SUCCESS;
    }

    /**
     * @throws \Throwable|PlatformMigrationException
     *
     * @return array{array<string,int>,array<string,int>}
     */
    private function getTargetRequiredDataOrFail(): array
    {
        try {
            /** @var array<array{id:int,name:string}> $connectorsResponse */
            $connectorsResponse = $this->getAllRequest(
                url: $this->targetUrl . '/api/latest/configuration/connectors',
                apiToken: $this->targetToken
            );

            /** @var array<array{id:int,name:string}> $graphTemplatesResponse */
            $graphTemplatesResponse = $this->getAllRequest(
                url: $this->targetUrl . '/api/latest/configuration/graphs/templates',
                apiToken: $this->targetToken
            );

            return [
                array_column($connectorsResponse, 'id', 'name'),
                array_column($graphTemplatesResponse, 'id', 'name'),
            ];
        } catch (PlatformMigrationException $ex) {

            throw PlatformMigrationException::failToRetrieveElements('connectors/graphTemplates', 'target', $ex);
        }
    }

    /**
     * @throws \Throwable|PlatformMigrationException
     *
     * @return array<string,int>
     */
    private function getTargetCommandsOrFail(): array
    {
        try {
            /** @var array<array{id:int,name:string}> $response */
            $response = $this->getAllRequest(
                url: $this->targetUrl . '/api/latest/configuration/commands',
                apiToken: $this->targetToken
            );

            return array_column($response['result'], 'id', 'name');
        } catch (PlatformMigrationException $ex) {
            throw PlatformMigrationException::failToRetrieveElements('commands', 'target', $ex);
        }
    }

    /**
     * @throws \Throwable|PlatformMigrationException
     *
     * @return array{array<int,_Argument[]>,array<int,_Macro[]>}
     */
    private function getSourceArgumentsAndMacrosOrFail(): array
    {
        try {
            $argumentStatement = $this->centreonDB->query(
                <<<'SQL'
                    SELECT cmd_id, macro_name as name, macro_description as description
                    FROM command_arg_description
                    SQL
            );

            $macroStatement = $this->centreonDB->query(
                <<<'SQL'
                    SELECT
                        command_command_id,
                        command_macro_name as name,
                        command_macro_type as type,
                        command_macro_desciption as description
                    FROM on_demand_macro_command
                    SQL
            );

            if ($argumentStatement === false || $macroStatement === false) {
                throw new \PDOException('Error during query');
            }

            return [
                $argumentStatement->fetchAll(\PDO::FETCH_GROUP),
                $macroStatement->fetchAll(\PDO::FETCH_GROUP),
            ];
        } catch (\Exception $ex) {
            throw PlatformMigrationException::failToRetrieveElements('arguments/macros', 'source', $ex);
        }
    }

    /**
     * @throws \Throwable|PlatformMigrationException
     *
     * @return _Command[]
     */
    private function getSourceCommandsOrFail(): array
    {
        try {

            $statement = $this->centreonDB->query(
                <<<'SQL'
                    SELECT
                        command.command_id,
                        command_name,
                        command.command_line,
                        command.command_type,
                        command.command_example,
                        command.enable_shell,
                        giv_graphs_template.name as graph_name,
                        connector.name as connector_name
                    FROM command
                    LEFT JOIN connector ON command.connector_id = connector.id
                    LEFT JOIN giv_graphs_template ON command.graph_id = giv_graphs_template.graph_id
                    WHERE
                        command_locked = 0
                        AND command_activate = '1'
                    SQL
            );

            if ($statement === false) {
                throw new \PDOException('Error during query');
            }

            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $ex) {
            throw PlatformMigrationException::failToRetrieveElements('commands', 'source', $ex);
        }
    }

    /**
     * @param array<string,int> $existingCommands
     * @param _Command $command
     *
     * @return bool
     */
    private function assertCommandExistOnTargetPlatform(array $existingCommands, array $command): bool
    {
        return array_key_exists($command['command_name'], $existingCommands);
    }

    /**
     * @param _Command $command
     * @param array<int,_Macro[]> $macros
     * @param array<int,_Argument[]> $arguments
     * @param array<string,int> $connectors
     * @param array<string,int> $graphTemplates
     *
     * @throws PlatformMigrationException
     *
     * @return int
     */
    private function createCommandOrFail(
        array $command,
        array $macros,
        array $arguments,
        array $connectors,
        array $graphTemplates
    ): int {
        /** @var array{id:int} $response */
        $response = $this->curlCall(
            url: $this->targetUrl . '/api/latest/configuration/commands',
            apiToken: $this->targetToken,
            method: 'POST',
            body: [
                'name' => $command['command_name'],
                'type' => $command['command_type'],
                'command_line' => $command['command_line'],
                'argument_example' => $command['command_example'],
                'is_shell' => (bool) $command['enable_shell'],
                'connector_id' => $command['connector_name'] !== null ? $connectors[$command['connector_name']] : null,
                'graph_template_id' => $command['graph_name'] !== null ? $graphTemplates[$command['graph_name']] : null,
                'arguments' => array_map(
                    fn(array $arg): array => [
                        'name' => $arg['name'],
                        'description' => $arg['description'] === '' ? null : $arg['description'],
                    ],
                    $arguments[$command['command_id']] ?? []
                ),
                'macros' => array_map(
                    fn(array $macro): array => [
                        'name' => $macro['name'],
                        'type' => (int) $macro['type'],
                        'description' => $macro['description'] === '' ? null : $macro['description'],
                    ],
                    $macros[$command['command_id']] ?? []
                ),
            ]
        );

        return (int) $response['id'];
    }
}