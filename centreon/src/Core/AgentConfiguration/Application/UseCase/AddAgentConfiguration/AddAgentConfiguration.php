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

namespace Core\AgentConfiguration\Application\UseCase\AddAgentConfiguration;

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
use Core\AgentConfiguration\Domain\Model\NewAgentConfiguration;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\AgentConfiguration\Domain\Model\Type;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;

final class AddAgentConfiguration
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadAgentConfigurationRepositoryInterface $readAcRepository,
        private readonly WriteAgentConfigurationRepositoryInterface $writeAcRepository,
        private readonly Validator $validator,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly ContactInterface $user,
    ) {
    }

    public function __invoke(
        AddAgentConfigurationRequest $request,
        AddAgentConfigurationPresenterInterface $presenter
    ): void {
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

            $request->pollerIds = array_unique($request->pollerIds);
            $type = Type::from($request->type);

            $this->validator->validateRequestOrFail($request);

            $newAc = AgentConfigurationFactory::createNewAgentConfiguration(
                name: $request->name,
                type: $type,
                parameters: $request->configuration,
            );

            [$module, $needBrokerDirectivePollers] = $this->checkNeedForBrokerDirective(
                $newAc,
                $request->pollerIds
            );

            $agentConfigurationId = $this->save(
                $newAc,
                $request->pollerIds,
                $module,
                $needBrokerDirectivePollers
            );

            if (null === $agentConfiguration = $this->readAcRepository->find($agentConfigurationId)) {
                throw AgentConfigurationException::errorWhileRetrievingObject();
            }

            $pollers = $this->readAcRepository->findPollersByAcId($agentConfigurationId);

            $presenter->presentResponse($this->createResponse($agentConfiguration, $pollers));
        } catch (AssertionFailedException|\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse(
                $ex instanceof AgentConfigurationException
                    ? $ex
                    : AgentConfigurationException::addAc()
            ));
        }
    }

    /**
     * @param NewAgentConfiguration $agentConfiguration
     * @param int[] $pollers
     * @param null|string $module
     * @param int[] $needBrokerDirectives
     *
     * @throws \Throwable
     *
     * @return int
     */
    private function save(NewAgentConfiguration $agentConfiguration, array $pollers, ?string $module, array $needBrokerDirectives): int
    {
        try {
            $this->dataStorageEngine->startTransaction();

            $newAcId = $this->writeAcRepository->add($agentConfiguration);
            $this->writeAcRepository->linkToPollers($newAcId, $pollers);
            if ($module !== null && $needBrokerDirectives !== []) {
                $this->writeAcRepository->addBrokerDirective($module, $needBrokerDirectives);
            }

            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'AddAgentConfiguration' transaction.");
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }

        return $newAcId;
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

    /**
     * @param AgentConfiguration $agentConfiguration
     * @param Poller[] $pollers
     *
     * @return AddAgentConfigurationResponse
     */
    private function createResponse(AgentConfiguration $agentConfiguration, array $pollers): AddAgentConfigurationResponse
    {
        return new AddAgentConfigurationResponse(
            id: $agentConfiguration->getId(),
            type: $agentConfiguration->getType(),
            name: $agentConfiguration->getName(),
            configuration: $agentConfiguration->getConfiguration()->getData(),
            pollers: $pollers
        );
    }
}
