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
 * This class is designed to send legacy "centcore" commands (SENDCFGFILE, RELOAD, RESTART, SYNCTRAP,
 * RELOADBROKER, …) to the central Gorgone server through the "legacycmd" module
 * (action CENTREONCOMMAND), instead of writing them to the local centcore pipe. legacycmd then
 * dispatches each command to its target poller. The endpoint runs on the central node, so there is
 * no "nodes/{id}/" prefix; the target poller is carried in the request body.
 *
 * The body is a JSON array of {"command": "<TYPE>", "target": <pollerId>} objects (Gorgone exposes
 * the whole POST body as the action "content", which the legacycmd module requires to be an array).
 *
 * @package Centreon\Domain\Gorgone\Command
 */
final class LegacyCmdCommand extends AbstractCommand implements CommandInterface
{
    private const NAME = 'centreon::legacycmd::command';

    /**
     * @inheritDoc
     */
    public function getUriRequest(): string
    {
        return 'centreon/legacycmd/command';
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
