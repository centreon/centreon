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

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Application\Validation\TypeValidatorInterface;
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
     * @param AddAgentConfigurationRequest $request
     *
     * @throws AgentConfigurationException|ValueError|AssertionException
     */
    public function validateRequestOrFail(AddAgentConfigurationRequest $request): void
    {
        $this->validateNameOrFail($request);
        $this->validatePollersOrFail($request);
        $this->validateTypeOrFail($request);
        $this->validateParametersOrFail($request);
    }

    /**
     * Validate that AC name is not already used.
     *
     * @param AddAgentConfigurationRequest $request
     *
     * @throws AgentConfigurationException
     */
    public function validateNameOrFail(AddAgentConfigurationRequest $request): void
    {
        $trimmedName = new TrimmedString($request->name);
        if ($this->readAcRepository->existsByName($trimmedName)) {
            throw AgentConfigurationException::nameAlreadyExists($trimmedName->value);
        }
    }

    /**
     * Validate that requesting user has access to pollers.
     * Check that pollers are not already linked to same AC type.
     *
     * @param AddAgentConfigurationRequest $request
     *
     * @throws AgentConfigurationException
     */
    public function validatePollersOrFail(AddAgentConfigurationRequest $request): void
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
        $unavailablePollers = [];
        foreach (Type::cases() as $type) {
            $unavailablePollers = array_merge(
                $unavailablePollers,
                $this->readAcRepository->findPollersByType($type)
            );
        }
        $pollerIds = array_map(fn(Poller $poller) => $poller->id, $unavailablePollers);

        if ([] !== $invalidPollers = array_intersect($pollerIds, $request->pollerIds)) {
            throw AgentConfigurationException::alreadyAssociatedPollers($invalidPollers);
        }
    }

    /**
     * Check type validity.
     *
     * @param AddAgentConfigurationRequest $request
     *
     * @throws ValueError
     */
    public function validateTypeOrFail(AddAgentConfigurationRequest $request): void
    {
        $type = Type::from($request->type);
    }

    /**
     * @param AddAgentConfigurationRequest $request
     *
     * @throws AgentConfigurationException|AssertionException
     */
    public function validateParametersOrFail(AddAgentConfigurationRequest $request): void
    {
        foreach ($this->parametersValidators as $validator) {
            if ($validator->isValidFor(Type::from($request->type))) {
                $validator->validateParametersOrFail($request);
            }
        }
    }
}
