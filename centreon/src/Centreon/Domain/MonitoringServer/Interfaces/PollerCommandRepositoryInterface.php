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

use Centreon\Domain\Engine\EngineException;

/**
 * Sends poller lifecycle commands (deploy configuration files, reload/restart the engine, reload the
 * broker, sync/reload/restart centreontrapd) to a monitoring server through Gorgone's legacycmd
 * module, instead of writing the local centcore pipe. This lets the web tier run on a host separate
 * from the collection stack.
 */
interface PollerCommandRepositoryInterface
{
    /**
     * Ask Gorgone to push the generated configuration files to the poller (legacy SENDCFGFILE).
     *
     * @param int $pollerId
     *
     * @throws EngineException
     */
    public function sendConfigFiles(int $pollerId): void;

    /**
     * Ask Gorgone to reload the poller's monitoring engine (legacy RELOAD).
     *
     * @param int $pollerId
     *
     * @throws EngineException
     */
    public function reloadEngine(int $pollerId): void;

    /**
     * Ask Gorgone to restart the poller's monitoring engine (legacy RESTART).
     *
     * @param int $pollerId
     *
     * @throws EngineException
     */
    public function restartEngine(int $pollerId): void;

    /**
     * Ask Gorgone to reload the poller's Centreon Broker (legacy RELOADBROKER).
     *
     * @param int $pollerId
     *
     * @throws EngineException
     */
    public function reloadBroker(int $pollerId): void;

    /**
     * Ask Gorgone to synchronize the poller's SNMP trap database (legacy SYNCTRAP).
     *
     * @param int $pollerId
     *
     * @throws EngineException
     */
    public function syncTrapConfiguration(int $pollerId): void;

    /**
     * Ask Gorgone to reload the poller's centreontrapd (legacy RELOADCENTREONTRAPD).
     *
     * @param int $pollerId
     *
     * @throws EngineException
     */
    public function reloadTrapd(int $pollerId): void;

    /**
     * Ask Gorgone to restart the poller's centreontrapd (legacy RESTARTCENTREONTRAPD).
     *
     * @param int $pollerId
     *
     * @throws EngineException
     */
    public function restartTrapd(int $pollerId): void;
}
