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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;

final class FindAgentConfiguration
{
    use LoggerTrait;

    /**
     * FindAgentConfiguration constructor.
     *
     * @param ContactInterface $user user requesting the agent configuration
     * @param ReadAgentConfigurationRepositoryInterface $readRepository repository to read agent configurations
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadAgentConfigurationRepositoryInterface $readRepository,
    ) {
    }

    /**
     * Retrieves an agent configuration with associated pollers.
     *
     * @param int $agentConfigurationId
     *
     * @return FindAgentConfigurationResponse|ResponseStatusInterface
     */
    public function __invoke(int $agentConfigurationId): FindAgentConfigurationResponse|ResponseStatusInterface
    {
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

            return new FindAgentConfigurationResponse($agentConfiguration, $pollers);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['user_id' => $this->user->getId()]);

            return new ErrorResponse(AgentConfigurationException::errorWhileRetrievingObject());
        }
    }
}
