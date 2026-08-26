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

namespace App\MonitoringConfiguration\Domain\Service;

use App\MonitoringConfiguration\Domain\Exception\GorgoneNodesSyncFailedException;

interface GorgoneNodesSynchronizer
{
    /**
     * Ask Gorgone to re-read its node list from the database.
     *
     * Not scoped to a single poller: Gorgone reloads every node at once. Callers must invoke
     * it once the poller rows are committed — Gorgone reads the database on its own
     * connection and would otherwise miss the new node.
     *
     * Returns once Gorgone has accepted the request, not once the reload has happened: the
     * reload is asynchronous and its outcome is not observable from here.
     *
     * @throws GorgoneNodesSyncFailedException when Gorgone refuses the command or is unreachable
     */
    public function synchronize(): void;
}
