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

namespace Core\AgentConfiguration\Application\UseCase\FindAgentConfigurations;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindAgentConfigurations
{
    use LoggerTrait;

    /**
     * @param ContactInterface $user
     * @param ReadAgentConfigurationRepositoryInterface $readRepository
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly ReadAgentConfigurationRepositoryInterface $readRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository
    ) {
    }

    /**
     * Finds all agent configurations.
     *
     * @param FindAgentConfigurationsPresenterInterface $presenter
     */
    public function __invoke(FindAgentConfigurationsPresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_POLLERS_AGENT_CONFIGURATIONS_RW)) {
                $this->error(
                    "User doesn't have sufficient rights to access agent configurations",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(AgentConfigurationException::accessNotAllowed())
                );

                return;
            }
            $agentConfigurations = $this->user->isAdmin()
                ? $this->readRepository->findAllByRequestParameters($this->requestParameters)
                : $this->readRepository->findAllByRequestParametersAndAccessGroups(
                    $this->requestParameters,
                    $this->readAccessGroupRepository->findByContact($this->user)
                );

            $presenter->presentResponse($this->createResponse($agentConfigurations));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['user_id' => $this->user->getId()]);
            $presenter->presentResponse(new ErrorResponse(AgentConfigurationException::errorWhileRetrievingObjects()));
        }
    }

    /**
     * Creates a response from the given array of agent configurations.
     *
     * @param AgentConfiguration[] $agentConfigurations
     * @return FindAgentConfigurationsResponse
     */
    private function createResponse(array $agentConfigurations): FindAgentConfigurationsResponse
    {
        $response = new FindAgentConfigurationsResponse();

        $agentConfigurationDtos = [];
        foreach ($agentConfigurations as $agentConfiguration) {
            $pollers = $this->readRepository->findPollersByAcId($agentConfiguration->getId());
            $agentConfigurationDto = new AgentConfigurationDto();
            $agentConfigurationDto->id = $agentConfiguration->getId();
            $agentConfigurationDto->type = $agentConfiguration->getType();
            $agentConfigurationDto->name = $agentConfiguration->getName();

            $agentConfigurationDto->pollers = array_map(function (Poller $poller) {
                $pollerDto = new PollerDto();
                $pollerDto->id = $poller->getId();
                $pollerDto->name = $poller->getName();

                return $pollerDto;
            }, $pollers);

            $agentConfigurationDtos[] = $agentConfigurationDto;
        }

        $response->agentConfigurations = $agentConfigurationDtos;

        return $response;
    }
}