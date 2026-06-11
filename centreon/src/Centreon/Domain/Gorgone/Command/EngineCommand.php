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

namespace Centreon\Domain\Gorgone\Command;

use Centreon\Domain\Gorgone\Interfaces\CommandInterface;

/**
 * This class is designed to send Engine external commands to the monitoring engine of a poller
 * through the "engine" module of the Gorgone server (action ENGINECOMMAND), which writes them to
 * the Engine command file. The body of the request is a JSON object of the form
 * {"commands": ["[<timestamp>] <COMMAND>;..."]} (Gorgone exposes the whole body as the action
 * "content", and the engine module reads content.commands).
 *
 * @package Centreon\Domain\Gorgone\Command
 */
final class EngineCommand extends AbstractCommand implements CommandInterface
{
    private const NAME = 'centreon::engine::command';

    /**
     * @inheritDoc
     */
    public function getUriRequest(): string
    {
        return 'nodes/' . $this->getMonitoringInstanceId() . '/centreon/engine/command';
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return self::NAME;
    }

    public function getMethod(): string
    {
        return self::METHOD_POST;
    }
}
