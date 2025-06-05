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
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Application\Validation\TypeValidatorInterface;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\AgentConfiguration\Domain\Model\Type;
use Core\Common\Domain\TrimmedString;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use ValueError;

class Validator
{
    use LoggerTrait;

    /** @var TypeValidatorInterface[] */
    private array $parametersValidators = [];

    /**
     * @param ReadAgentConfigurationRepositoryInterface $readAcRepository
     * @param ContactInterface $user
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository
     * @param \Traversable<TypeValidatorInterface> $parametersValidators
     */
    public function __construct(
        private readonly ReadAgentConfigurationRepositoryInterface $readAcRepository,
        private readonly ContactInterface $user,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository,
        \Traversable $parametersValidators,
    ) {
        $this->parametersValidators = iterator_to_array($parametersValidators);
    }

    /**
     * @param UpdateAgentConfigurationRequest $request
     * @param AgentConfiguration $agentConfiguration
     *
     * @throws AgentConfigurationException|ValueError|AssertionFailedException
     */
    public function validateRequestOrFail(
        UpdateAgentConfigurationRequest $request,
        AgentConfiguration $agentConfiguration
    ): void
    {
        $this->validateNameOrFail($request, $agentConfiguration);
        $this->validatePollersOrFail($request, $agentConfiguration);
        $this->validateTypeOrFail($request, $agentConfiguration);
        $this->validateParametersOrFail($request);
    }

    /**
     * Validate that AC name is not already used.
     *
     * @param UpdateAgentConfigurationRequest $request
     * @param AgentConfiguration $agentConfiguration
     *
     * @throws AgentConfigurationException
     */
    public function validateNameOrFail(
        UpdateAgentConfigurationRequest $request,
        AgentConfiguration $agentConfiguration
        ): void
    {
        $trimmedName = new TrimmedString($request->name);

        if (
            $agentConfiguration->getName() !== $trimmedName->value
            && $this->readAcRepository->existsByName($trimmedName)
        ) {
            throw AgentConfigurationException::nameAlreadyExists($trimmedName->value);
        }
    }

    /**
     * Check type validity.
     *
     * @param UpdateAgentConfigurationRequest $request
     * @param AgentConfiguration $agentConfiguration
     *
     * @throws AgentConfigurationException|ValueError
     */
    public function validateTypeOrFail(
        UpdateAgentConfigurationRequest $request,
        AgentConfiguration $agentConfiguration
    ): void
    {
        $type = Type::from($request->type);

        if ($type->name !== $agentConfiguration->getType()->name) {
            throw AgentConfigurationException::typeChangeNotAllowed();
        }
    }

    /**
     * Validate that requesting user has access to pollers.
     * Check that pollers are not already linked to same AC type.
     *
     * @param UpdateAgentConfigurationRequest $request
     * @param AgentConfiguration $agentConfiguration
     *
     * @throws AgentConfigurationException
     */
    public function validatePollersOrFail(
        UpdateAgentConfigurationRequest $request,
        AgentConfiguration $agentConfiguration
    ): void
    {
        if ([] === $request->pollerIds) {
            throw AgentConfigurationException::arrayCanNotBeEmpty('pollerIds');
        }

        // Check pollers have valid IDs according to user permissions.
        $invalidPollers = [];
        foreach ($request->pollerIds as $pollerId) {
            $isPollerIdValid = false;
            if ($this->user->isAdmin()) {
                $isPollerIdValid = $this->readMonitoringServerRepository->exists($pollerId);
            } else {
                $agentConfigurationcessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $isPollerIdValid = $this->readMonitoringServerRepository->existsByAccessGroups($pollerId, $agentConfigurationcessGroups);
            }

            if (false === $isPollerIdValid) {
                $invalidPollers[] = $pollerId;
            }
        }

        if ($invalidPollers !== []) {
            throw AgentConfigurationException::idsDoNotExist('pollerIds', $invalidPollers);
        }

        // Check pollers are not already associated to an AC.
        $actualPollers = $this->readAcRepository->findPollersByAcId($agentConfiguration->getId());
        $actualPollerIds = array_map(fn(Poller $poller) => $poller->id, $actualPollers);

        $unavailablePollers = [];
        foreach (Type::cases() as $type) {
            $unavailablePollers = array_merge(
                $unavailablePollers,
                $this->readAcRepository->findPollersByType($type)
            );
        }
        $unavailablePollerIds = array_map(fn(Poller $poller) => $poller->id, $unavailablePollers);
        $unavailablePollerIds = array_diff($unavailablePollerIds, $actualPollerIds);

        if ([] !== $invalidPollers = array_intersect($unavailablePollerIds, $request->pollerIds)) {
            throw AgentConfigurationException::alreadyAssociatedPollers($invalidPollers);
        }
    }

    /**
     * @param UpdateAgentConfigurationRequest $request
     *
     * @throws AgentConfigurationException|AssertionFailedException
     */
    public function validateParametersOrFail(UpdateAgentConfigurationRequest $request): void
    {
        foreach ($this->parametersValidators as $validator) {
            if ($validator->isValidFor(Type::from($request->type))) {
                $validator->validateParametersOrFail($request);
            }
        }
    }
}
