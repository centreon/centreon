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

namespace Core\RemoteChannel\Infrastructure\Gorgone;

use Centreon\Infrastructure\Gorgone\LegacyCommandSender;
use Core\RemoteChannel\Application\ConfigurationDeployerInterface;
use Core\RemoteChannel\Domain\Model\DeploymentAction;

/**
 * Historical behaviour: SENDCFGFILE then RELOAD/RESTART through Gorgone legacycmd. The legacy export scripts
 * keep their own Gorgone code (central local copy, VMware...); this adapter is the port view of it.
 */
final class GorgoneConfigurationDeployer implements ConfigurationDeployerInterface
{
    public function __construct(private readonly LegacyCommandSender $legacyCommandSender)
    {
    }

    public function deploy(array $targets, DeploymentAction $action, bool $withFiles): array
    {
        $engineCommand = match ($action) {
            DeploymentAction::Reload => 'RELOAD',
            DeploymentAction::Restart => 'RESTART',
            DeploymentAction::None => null,
        };
        $sender = $this->legacyCommandSender;
        $deliveries = [];
        foreach ($targets as $target) {
            $pollerId = $target->id;
            $deliveries[$pollerId] = static function () use ($sender, $pollerId, $withFiles, $engineCommand): void {
                if ($withFiles) {
                    $sender->sendConfigurationFiles($pollerId);
                }
                if ($engineCommand !== null) {
                    $sender->sendCommand($engineCommand, $pollerId);
                }
            };
        }

        return $sender->deliverToPollers($deliveries);
    }
}
