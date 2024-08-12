<?php

/*
 * Copyright 2005 - 2020 Centreon (https://www.centreon.com/)
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
declare(strict_types=1);

namespace Centreon\Infrastructure\Engine;

use Centreon\Domain\Engine\EngineException;
use Centreon\Domain\Engine\Interfaces\EngineRepositoryInterface;

final class EngineRepositoryFile implements EngineRepositoryInterface
{
    /**
     * @var string
     */
    private $centCoreDirectory;

    /**
     * @var string
     */
    private $centCoreFile;

    /**
     * EngineRepositoryFile constructor.
     *
     * @param string $centCoreDirectory
     */
    public function __construct(string $centCoreDirectory)
    {
        $this->centCoreDirectory = $centCoreDirectory;
        $this->centCoreFile =
            $centCoreDirectory
            . DIRECTORY_SEPARATOR
            . 'external-cmd-' . microtime(true) . '.cmd';
    }

    /**
     * @inheritDoc
     */
    public function sendExternalCommands(array $commands): void
    {
        $this->send($commands);
    }

    /**
     * @inheritDoc
     */
    public function sendExternalCommand(string $command): void
    {
        $this->send([$command]);
    }

    public function sendExternalCommandsBypassGorgone(array $commands): void
    {
        $this->sendToEngineBypassGorgone($commands);
    }

    /**
     * @inheritDoc
     */
    public function sendExternalCommandBypassGorgone(string $command): void
    {
        $this->sendToEngineBypassGorgone([$command]);
    }

    /**
     * Send all data that has been waiting to be sent.
     *
     * @param array $commandsAwaiting
     * @return int Returns the number of commands sent
     * @throws EngineException
     */
    private function send(array $commandsAwaiting): int
    {
        $commandsToSend = '';
        foreach ($commandsAwaiting as $command) {
            $commandsToSend .= !empty($commandsToSend) ? "\n" : '';
            $commandsToSend .= $command;
        }

        if (!is_dir($this->centCoreDirectory)) {
            throw new EngineException(
                sprintf(_('Centcore directory %s does not exist'), $this->centCoreDirectory)
            );
        }

        if (!empty($commandsToSend)) {
            $isDataSent = file_put_contents($this->centCoreFile, $commandsToSend . "\n", FILE_APPEND);

            if ($isDataSent === false) {
                throw new EngineException(
                    sprintf(
                        _('Error during creation of the CentCore command file (%s)'),
                        $this->centCoreFile
                    )
                );
            }
        }

        return count($commandsAwaiting);
    }
    
    // Write directly in the central centengine command file without going through a external command gorgone.
    private function sendToEngineBypassGorgone(array $commandsAwaiting): int
    {
        $centengine_cmd_file_name = "centengine.cmd";
        $centengine_cmd_dir       = "/var/lib/centreon-engine/rw/";
        $centengine_cmd_path      = $centengine_cmd_dir.$centengine_cmd_file_name;
        
        $commandsToSend = '';
        foreach ($commandsAwaiting as $command) {
            $commandsToSend .= !empty($commandsToSend) ? "\n" : '';
            $commandsToSend .= $command;
        }

        if (!is_dir($centengine_cmd_dir)) {
            throw new EngineException(
                sprintf(_('Centengine directory %s does not exist'), $centengine_cmd_dir)
            );
        }

        if (!empty($commandsToSend)) {
            $isDataSent = file_put_contents($centengine_cmd_path, $commandsToSend . "\n", FILE_APPEND);

            if ($isDataSent === false) {
                throw new EngineException(
                    sprintf(
                        _('Error during creation of the CentCore command file (%s)'),
                        $centengine_cmd_path
                    )
                );
            }
        }

        return count($commandsAwaiting);
    }

}
