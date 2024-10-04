<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\AgentConfiguration\Application\UseCase\FindAgentConfiguration;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Application\Common\UseCase\StandardPresenterInterface;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindAgentConfiguration
{
    use LoggerTrait;

    /**
     * FindAgentConfiguration constructor.
     *
     * @param ContactInterface $user user requesting the agent configuration
     * @param ReadAgentConfigurationRepositoryInterface $readRepository repository to read agent configurations
     * @param ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository repository to read monitoring servers
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository repository to read access groups
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadAgentConfigurationRepositoryInterface $readRepository,
        private readonly ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
    ) {
    }

    /**
     * Finds an agent configuration.
     *
     * @param int $agentConfigurationId
     * @param FindAgentConfigurationResponse|ResponseStatusInterface $presenter
     *
     * @throws \Throwable
     */
    public function __invoke(int $agentConfigurationId): FindAgentConfigurationResponse|ResponseStatusInterface {
        $this->info(
            'Find agent configuration',
            [
                'user_id' => $this->user->getId(),
                'agent_configuration_id' => $agentConfigurationId,
            ]
        );

        try {
            if (null === $agentConfiguration = $this->readRepository->find($agentConfigurationId)) {
                $this->error(
                    'Agent configuration not found',
                    ['agent_configuration_id' => $agentConfigurationId]
                );

                return new NotFoundResponse('Agent Configuration');
            }

            $this->info(
                'Retrieved agent configuration',
                ['agent_configuration_id' => $agentConfigurationId]
            );

            $pollers = $this->readRepository->findPollersByAcId($agentConfigurationId);

            if (! $this->user->isAdmin()) {
                $pollerIds = array_map(
                    static fn(Poller $poller): int => $poller->id,
                    $pollers
                );
                $validPollerIds = $this->readMonitoringServerRepository->existByAccessGroups(
                    $pollerIds,
                    $this->readAccessGroupRepository->findByContact($this->user)
                );

                if ([] !== array_diff($pollerIds, $validPollerIds)) {
                    $this->debug(
                        'User does not have the correct access groups for pollers',
                        [
                            'user_id' => $this->user->getId(),
                            'poller_ids' => array_diff($pollerIds, $validPollerIds),
                        ]
                    );

                    return new NotFoundResponse('Agent Configuration');
                }
            }

            return new FindAgentConfigurationResponse($agentConfiguration, $pollers);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['user_id' => $this->user->getId()]);

            return new ErrorResponse(AgentConfigurationException::errorWhileRetrievingObject());
        }
    }
}
