<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\RemoteChannel\Application;

use Core\RemoteChannel\Domain\Model\DeploymentAction;
use Core\RemoteChannel\Domain\Model\PollerTarget;

/**
 * Deploys the generated configuration of pollers and reloads (or restarts) their engine.
 * Every poller is handled at once; a poller that fails does not stop the others.
 */
interface ConfigurationDeployerInterface
{
    /**
     * @param list<PollerTarget> $targets
     * @param bool $withFiles true: send the generated files (move step), false: only reload/restart
     *
     * @return array<int, \Throwable> by poller id, the failure of each poller not deployed
     */
    public function deploy(array $targets, DeploymentAction $action, bool $withFiles): array;
}
