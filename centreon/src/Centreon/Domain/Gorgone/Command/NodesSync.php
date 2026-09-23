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
use LogicException;

/**
 * Tells the Gorgone `centreon` module to re-sync its list of connected nodes.
 * Sent after a poller is provisioned in pullwss mode so the central picks it up
 * immediately.
 *
 * Not scoped to a specific poller: the URI is a static endpoint. The
 * monitoringInstanceId inherited from AbstractCommand is therefore unused —
 * getMonitoringInstanceId() throws to prevent any downstream code (e.g.
 * ResponseRepositoryAPI, which builds /api/nodes/{id}/log/...) from silently
 * hitting /api/nodes/0/... with the sentinel value.
 */
final class NodesSync extends AbstractCommand implements CommandInterface
{
    private const NAME = 'centreon::nodes::sync';
    private const URI = 'centreon/nodes/sync';

    public function __construct()
    {
        // The 0 monitoring-instance id is a sentinel — see getMonitoringInstanceId().
        parent::__construct(0, '{}');
    }

    public function getUriRequest(): string
    {
        return self::URI;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getMethod(): string
    {
        return self::METHOD_POST;
    }

    public function getMonitoringInstanceId(): int
    {
        throw new LogicException(
            'NodesSync is not scoped to a poller: getMonitoringInstanceId() must not be called.',
        );
    }
}
