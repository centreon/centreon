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

/**
 * Class
 *
 * @class Command
 */
class Command extends AbstractObject
{
    /** @var null */
    private $commands = null;

    /** @var null */
    private $mail_bin = null;

    /** @var string */
    protected $generate_filename = 'commands.cfg';

    /** @var string */
    protected string $object_name = 'command';

    /** @var string */
    protected $attributes_select = '
        command_id,
        command_name,
        command.command_line as command_line_base,
        connector.name as connector,
        enable_shell
    ';

    /** @var string[] */
    protected $attributes_write = ['command_name', 'command_line', 'connector'];

    /**
     * Create the cache of commands.
     */
    private function createCommandsCache(): void
    {
        $query = "SELECT {$this->attributes_select} FROM command "
            . "LEFT JOIN connector ON connector.id = command.connector_id AND connector.enabled = '1' "
            . "AND command.command_activate = '1'";
        $stmt = $this->backend_instance->db->prepare($query);
        $stmt->execute();
        $this->commands = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    }

    /**
     * @throws PDOException
     * @return void
     */
    private function getMailBin(): void
    {
        $stmt = $this->backend_instance->db->prepare("SELECT
              options.value
            FROM options
                WHERE options.key = 'mailer_path_bin'
            ");
        $stmt->execute();
        $this->mail_bin = ($row = $stmt->fetch(PDO::FETCH_ASSOC)) ? $row['value'] : '';
    }

    /**
     * @param $command_id
     *
     * @throws LogicException
     * @throws PDOException
     * @throws Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @return mixed|null
     */
    public function generateFromCommandId($command_id)
    {
        $name = null;
        if (is_null($this->commands)) {
            $this->createCommandsCache();
        }

        if (! isset($this->commands[$command_id])) {
            return null;
        }
        if ($this->checkGenerate($command_id)) {
            return $this->commands[$command_id]['command_name'];
        }

        if (is_null($this->mail_bin)) {
            $this->getMailBin();
        }

        if (! $this->isVaultEnabled) {
            $this->getVaultConfigurationStatus();
        }

        // enable_shell is 0 we remove it
        $command_line = html_entity_decode($this->commands[$command_id]['command_line_base'] ?? '');
        $command_line = str_replace('#BR#', '\\n', $command_line);
        $command_line = str_replace('@MAILER@', $this->mail_bin, $command_line);
        $command_line = str_replace("\n", " \\\n", $command_line);
        $command_line = str_replace("\r", '', $command_line);

        if (
            $this->isVaultEnabled
            && preg_match('/\\$CENTREONPLUGINS\\$\\/centreon/', $command_line)
        ) {
            $command_line .= ' --pass-manager=centreonvault';
        }

        if (! is_null($this->commands[$command_id]['enable_shell'])
            && $this->commands[$command_id]['enable_shell'] == 1
        ) {
            $command_line = '/bin/sh -c ' . escapeshellarg($command_line);
        }

        $this->generateObjectInFile(
            array_merge($this->commands[$command_id], ['command_line' => $command_line]),
            $command_id
        );

        return $this->commands[$command_id]['command_name'];
    }

    /**
     * Get information of command.
     *
     * @param int $commandId
     * @return array{
     *     command_name: string,
     *     command_line_base: string,
     *     connector: string|null,
     *     enable_shell: string
     *  }|null
     */
    public function findCommandById(int $commandId): ?array
    {
        if (is_null($this->commands)) {
            $this->createCommandsCache();
        }

        return $this->commands[$commandId] ?? null;
    }
}
