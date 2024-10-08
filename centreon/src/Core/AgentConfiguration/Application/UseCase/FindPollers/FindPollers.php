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

namespace Core\AgentConfiguration\Application\UseCase\FindPollers;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class FindPollers
{
    use LoggerTrait;

    /**
     * @param ContactInterface $user The user which requested the pollers.
     * @param RequestParametersInterface $requestParameters The request parameters to filter the pollers.
     * @param ReadAgentConfigurationRepositoryInterface $readRepository The repository to read the agent configurations.
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository The repository to read the access groups.
     */
    public function __construct(
        private readonly ContactInterface $user,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadAgentConfigurationRepositoryInterface $readRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
    ) {
    }

    public function __invoke(): FindPollersResponse|ResponseStatusInterface
    {
        try {
            $pollers =  $this->user->isAdmin()
                ? $this->readRepository->findAvailablePollersByRequestParameters($this->requestParameters)
                : $this->readRepository->findAvailablePollersByRequestParametersAndAccessGroups(
                    $this->readAccessGroupRepository->findByContact($this->user),
                    $this->requestParameters
                );

            return new FindPollersResponse($pollers);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            return new ErrorResponse(AgentConfigurationException::findPollers());
        }
    }
}
