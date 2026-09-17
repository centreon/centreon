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

namespace App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerConfigKey;
use App\MonitoringConfiguration\Domain\Factory\BrokerConfigurationFactory;
use App\MonitoringConfiguration\Domain\Repository\BrokerConfigurationRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Domain\VaultInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommandHandler]
final readonly class CreateBrokerConfigurationCommandHandler
{
    public function __construct(
        private BrokerConfigurationRepository $repository,
        private BrokerConfigurationFactory $factory,
        private VaultInterface $vault,
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private bool $isCloudPlatform = false,
    ) {
    }

    public function __invoke(CreateBrokerConfigurationCommand $command): void
    {
        // On cloud the BBDO Client output must carry the same authorization token as the Central
        // broker's BBDO Server input (throws → the whole create-poller transaction rolls back).
        $authorizationToken = $this->isCloudPlatform
            ? $this->resolveAuthorizationToken()
            : null;

        $brokerConfiguration = $this->factory->createDefault(
            pollerId: $command->pollerId,
            pollerName: $command->pollerName,
            isCloudPlatform: $this->isCloudPlatform,
            centralAddress: $command->centralAddress,
            authorizationToken: $authorizationToken,
        );

        $this->repository->add($brokerConfiguration);
    }

    /**
     * Resolve the Central BBDO Server authorization token for the new poller's output.
     *
     * When the Central stores the token in the vault, resolve it to its plaintext then re-vault
     * it under a fresh UUID (one per broker configuration) so the poller output references its
     * own vault entry rather than the Central's; the plaintext is never persisted. A plaintext
     * Central token (vault disabled) is used as-is.
     *
     * Any vault read/write failure propagates, rolling back the whole create-poller transaction.
     */
    private function resolveAuthorizationToken(): string
    {
        $rawToken = $this->repository->getCentralBbdoServerAuthorizationToken();

        if (! $this->vault->isEnabled('vault_broker') || ! $this->vault->isVaultPath($rawToken)) {
            return $rawToken;
        }

        $plaintext = $this->vault->resolve($rawToken);

        return $this->vault->write(
            BrokerConfigurationFactory::BROKER_VAULT_CUSTOM_PATH,
            BrokerConfigurationFactory::CENTRAL_MODULE_OUTPUT_NAME . '_' . BrokerConfigKey::AUTHORIZATION,
            $plaintext,
        );
    }
}
