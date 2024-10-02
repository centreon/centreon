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

namespace Core\AgentConfiguration\Application\UseCase\UpdateAgentConfiguration;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\Factory\AgentConfigurationFactory;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Application\Repository\WriteAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class UpdateAgentConfiguration
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadAgentConfigurationRepositoryInterface $readAcRepository,
        private readonly WriteAgentConfigurationRepositoryInterface $writeAcRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository,
        private readonly Validator $validator,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly ContactInterface $user,
    ) {
    }

    public function __invoke(
        UpdateAgentConfigurationRequest $request,
        PresenterInterface $presenter
    ): void {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_POLLERS_AGENT_CONFIGURATIONS_RW)) {
                $this->error(
                    "User doesn't have sufficient rights to access agent configurations",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(AgentConfigurationException::accessNotAllowed())
                );

                return;
            }

            if (null === $agentConfiguration = $this->getAgentConfiguration($request->id)) {
                $presenter->setResponseStatus(
                    new NotFoundResponse('Agent Configuration')
                );

                return;
            }

            $request->pollerIds = array_unique($request->pollerIds);

            $this->validator->validateRequestOrFail($request, $agentConfiguration);

            $updatedAgentConfiguration = AgentConfigurationFactory::createAgentConfiguration(
                id: $agentConfiguration->getId(),
                name: $request->name,
                type: $agentConfiguration->getType(),
                parameters: $request->configuration
            );

            $this->save($updatedAgentConfiguration, $request->pollerIds);

            $presenter->setResponseStatus(New NoContentResponse());
        } catch (AssertionFailedException|\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse(
                $ex instanceof AgentConfigurationException
                    ? $ex
                    : AgentConfigurationException::updateAc()
            ));
        }
    }

    /**
     * Get AC based on user rights.
     *
     * @param int $id
     *
     * @throws \Throwable
     *
     * @return null|AgentConfiguration
     */
    private function getAgentConfiguration(int $id): null|AgentConfiguration
    {
        if (null === $agentConfiguration = $this->readAcRepository->find($id)) {

            return null;
        }

        if (! $this->user->isAdmin()) {
            $pollerIds = array_map(
                static fn(Poller $poller): int => $poller->id,
                $this->readAcRepository->findPollersByAcId($agentConfiguration->getId())
            );
            $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
            $validPollerIds = $this->readMonitoringServerRepository->existByAccessGroups($pollerIds, $accessGroups);

            if ([] !== array_diff($pollerIds, $validPollerIds)) {

                return null;
            }
        }

        return $agentConfiguration;
    }

    /**
     * @param AgentConfiguration $agentConfiguration
     * @param int[] $pollers
     *
     * @throws \Throwable
     */
    private function save(AgentConfiguration $agentConfiguration, array $pollers): void
    {
        try {
            $this->dataStorageEngine->startTransaction();

            $this->writeAcRepository->update($agentConfiguration);
            $this->writeAcRepository->removePollers($agentConfiguration->getId());
            $this->writeAcRepository->linkToPollers($agentConfiguration->getId(), $pollers);

            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'UpdateAgentConfiguration' transaction.");
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }
    }
}
