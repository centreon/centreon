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

namespace Centreon\Infrastructure\RemoteChannel;

use App\Kernel;
use Centreon\Domain\MonitoringServer\Exception\GorgoneDeliveryException;
use Centreon\Infrastructure\Gorgone\LegacyCommandException;
use Core\RemoteChannel\Application\ConfigurationDeployerInterface;
use Core\RemoteChannel\Application\Repository\ReadPollerTargetRepositoryInterface;
use Core\RemoteChannel\Domain\Exception\RemoteChannelException;
use Core\RemoteChannel\Domain\Model\DeploymentAction;
use Core\RemoteChannel\Domain\Model\DispatchStatus;
use Core\RemoteChannel\Domain\Model\PollerTarget;

/**
 * Glue for the legacy export scripts (moveFiles.php, restartPollers.php): picks the pollers reached through the
 * central broker (nagios_server.remote_channel = 'broker') and reports their failures as the Gorgone delivery
 * failures those scripts already handle, so the UI and the REST export (MonitoringServerController) answer the
 * same way whatever the channel.
 */
class LegacyRemoteChannelBridge
{
    public function __construct(
        private readonly ReadPollerTargetRepositoryInterface $pollerTargetRepository,
        private readonly ConfigurationDeployerInterface $deployer,
    ) {
    }

    public static function create(): self
    {
        $container = Kernel::createForWeb()->getContainer();
        $repository = $container->get(ReadPollerTargetRepositoryInterface::class);
        $deployer = $container->get(ConfigurationDeployerInterface::class);
        \assert($repository instanceof ReadPollerTargetRepositoryInterface);
        \assert($deployer instanceof ConfigurationDeployerInterface);

        return new self($repository, $deployer);
    }

    /**
     * @param iterable<int|string> $pollerIds
     *
     * @return array<int, PollerTarget> the pollers reached through the central broker, by id
     */
    public function findBrokerPollers(iterable $pollerIds): array
    {
        $targets = [];
        foreach ($pollerIds as $pollerId) {
            $target = $this->pollerTargetRepository->findByIdOrUid((int) $pollerId);
            if ($target !== null && $target->isBroker() && $target->id === (int) $pollerId) {
                $targets[$target->id] = $target;
            }
        }

        return $targets;
    }

    /**
     * @param array<int, PollerTarget> $targets
     *
     * @return array<int, LegacyCommandException> by poller id, the failure of each poller not deployed
     */
    public function deploy(array $targets, DeploymentAction $action, bool $withFiles): array
    {
        if ($targets === []) {
            return [];
        }

        return array_map(
            self::toLegacyFailure(...),
            $this->deployer->deploy(array_values($targets), $action, $withFiles)
        );
    }

    public static function toLegacyFailure(\Throwable $failure): LegacyCommandException
    {
        if ($failure instanceof LegacyCommandException) {
            return $failure;
        }
        $status = $failure instanceof RemoteChannelException ? $failure->status : null;
        $reason = match ($status) {
            DispatchStatus::NotConnected, DispatchStatus::PeerTooOld => GorgoneDeliveryException::NODE_NOT_CONNECTED,
            DispatchStatus::Timeout => GorgoneDeliveryException::RESULT_TIMEOUT,
            default => GorgoneDeliveryException::DELIVERY_FAILED,
        };

        return new LegacyCommandException($failure->getMessage(), $reason, $failure);
    }
}
