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

namespace Centreon\Domain\MonitoringServer\Interfaces;

use Centreon\Domain\Gorgone\GorgoneException;

/**
 * Retrieves a poller's Centreon Broker statistics JSON over the network (Gorgone), instead of reading
 * the local broker-stats cache file. This lets the web tier display broker statistics for a poller
 * running on a host separate from the collection stack, without a shared filesystem.
 */
interface BrokerStatsRepositoryInterface
{
    /**
     * Fetch the raw content of a broker statistics file from the given poller through Gorgone.
     *
     * @param int $pollerId monitoring server id the statistics file lives on
     * @param string $statsFilePath absolute path of the "-stats.json" file on that poller
     *
     * @throws GorgoneException when the command cannot be sent or the file cannot be read remotely
     *
     * @return string|null the file content, or null if no result was returned in time
     */
    public function getStatsContent(int $pollerId, string $statsFilePath): ?string;
}
