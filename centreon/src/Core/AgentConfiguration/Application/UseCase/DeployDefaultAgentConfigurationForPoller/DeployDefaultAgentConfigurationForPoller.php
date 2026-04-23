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

namespace Core\AgentConfiguration\Application\UseCase\DeployDefaultAgentConfigurationForPoller;

use Centreon\Domain\Log\LoggerTrait;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Application\Repository\WriteAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\CmaConfigurationParameters;
use Core\AgentConfiguration\Domain\Model\ConnectionModeEnum;
use Core\AgentConfiguration\Domain\Model\NewAgentConfiguration;
use Core\AgentConfiguration\Domain\Model\Type;
use Core\Common\Application\Repository\RepositoryManagerInterface;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\Token\Application\Repository\WriteTokenRepositoryInterface;
use Core\Security\Token\Domain\Model\TokenFactory;
use Core\Security\Token\Domain\Model\TokenTypeEnum;
use Symfony\Component\Uid\UuidV4;

final class DeployDefaultAgentConfigurationForPoller
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteAgentConfigurationRepositoryInterface $writeAcRepository,
        private readonly ReadAgentConfigurationRepositoryInterface $readAcRepository,
        private readonly ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository,
        private readonly WriteTokenRepositoryInterface $writeTokenRepository,
        private readonly RepositoryManagerInterface $repositoryManager,
    ) {
    }

    public function __invoke(DeployDefaultAgentConfigurationForPollerRequest $request): void
    {
        if ($this->readAcRepository->findByPollerId($request->pollerId) !== null) {
            $this->info(
                'Default AC deployment skipped for poller because it already has an AC linked.',
                ['pollerId' => $request->pollerId]
            );

            return;
        }

        $poller = $this->readMonitoringServerRepository->get($request->pollerId);
        $suffix = mb_substr(UuidV4::v4()->toString(), 0, 8);

        $token = TokenFactory::createNew(
            type: TokenTypeEnum::CMA,
            data: [
                'name' => $poller->getName() . '-' . $suffix,
                'user_id' => null,
                'creator_id' => $request->creatorId,
                'creator_name' => $request->creatorName,
                'expiration_date' => null,
                'configuration_provider_id' => null,
            ]
        );
        $agentConfiguration = new NewAgentConfiguration(
            name: 'AC-' . $poller->getName() . '-' . $suffix,
            type: Type::CMA,
            connectionMode: ConnectionModeEnum::SECURE,
            configuration: new CmaConfigurationParameters([
                'port' => AgentConfiguration::DEFAULT_PORT,
                'hosts' => [],
                'tokens' => [
                    [
                        'name' => $token->getName(),
                        'creator_id' => $token->getCreatorId(),
                    ],
                ],
                'agent_initiated' => true,
                'otel_private_key' => null,
                'poller_initiated' => false,
                'otel_ca_certificate' => null,
                'otel_public_certificate' => null,
            ])
        );
        [$module, $needBrokerDirectivePollers] = $this->checkNeedForBrokerDirective(
            $agentConfiguration,
            [$request->pollerId]
        );

        $this->repositoryManager->startTransaction();
        try {
            $this->writeTokenRepository->add($token);
            $agentConfigurationId = $this->writeAcRepository->add($agentConfiguration);
            $this->writeAcRepository->linkToPollers($agentConfigurationId, [$poller->getId()]);
            if ($module !== null && $needBrokerDirectivePollers !== []) {
                $this->writeAcRepository->addBrokerDirective($module, $needBrokerDirectivePollers);
            }
            $this->repositoryManager->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'DeployDefaultAgentConfigurationForPoller' transaction.");
            $this->repositoryManager->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * Return the module directive and the poller IDs that need the AC type related broker directive to be added.
     *
     * @param NewAgentConfiguration $newAc
     * @param int[] $pollerIds
     *
     * @throws \Throwable
     *
     * @return array{?string,int[]}
     */
    private function checkNeedForBrokerDirective(NewAgentConfiguration $newAc, array $pollerIds): array
    {
        $module = $newAc->getConfiguration()->getBrokerDirective();
        $needBrokerDirectivePollers = [];
        if ($module !== null) {
            $haveBrokerDirectivePollers = $this->readAcRepository->findPollersWithBrokerDirective(
                $module
            );
            $needBrokerDirectivePollers = array_diff(
                $pollerIds,
                $haveBrokerDirectivePollers
            );
        }

        return [$module, $needBrokerDirectivePollers];
    }
}
