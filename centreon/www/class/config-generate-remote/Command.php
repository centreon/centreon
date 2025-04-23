<?php
/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace ConfigGenerateRemote;

use PDO;
use ConfigGenerateRemote\Abstracts\AbstractObject;

/**
 * Class
 *
 * @class Command
 * @package ConfigGenerateRemote
 */
class Command extends AbstractObject
{
    /** @var array|null */
    private $commands = null;
    /** @var string */
    protected $table = 'command';
    /** @var string */
    protected $generateFilename = 'commands.infile';
    /** @var string */
    protected $attributesSelect = '
        command_id,
        command_name,
        command_line,
        command_type,
        enable_shell,
        graph_id
    ';
    /** @var string[] */
    protected $attributesWrite = [
        'command_id',
        'command_name',
        'command_line',
        'command_type',
        'enable_shell',
        'graph_id'
    ];

    /**
     * Get commands
     *
     * @return void
     */
    private function getCommands(): void
    {
        $query = "SELECT $this->attributesSelect FROM command";
        $stmt = $this->backendInstance->db->prepare($query);
        $stmt->execute();
        $this->commands = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    }

    /**
     * Generate command and get command name
     *
     * @param null|int $commandId
     *
     * @return string|null
     * @throws \Exception
     */
    public function generateFromCommandId(?int $commandId): ?string
    {
        if (is_null($this->commands)) {
            $this->getCommands();
        }

        if (!isset($this->commands[$commandId])) {
            return null;
        }
        if ($this->checkGenerate($commandId)) {
            return $this->commands[$commandId]['command_name'];
        }

        Graph::getInstance($this->dependencyInjector)->getGraphFromId($this->commands[$commandId]['graph_id']);
        $this->commands[$commandId]['command_id'] = $commandId;
        $this->generateObjectInFile(
            $this->commands[$commandId],
            $commandId
        );

        return $this->commands[$commandId]['command_name'];
    }
}
