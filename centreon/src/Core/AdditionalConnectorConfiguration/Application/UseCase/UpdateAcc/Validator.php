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

namespace Core\AdditionalConnectorConfiguration\Application\UseCase\UpdateAcc;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\AdditionalConnectorConfiguration\Application\Exception\AccException;
use Core\AdditionalConnectorConfiguration\Application\Repository\ReadAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Application\Validation\TypeDataValidatorInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\Poller;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
use Core\Common\Domain\TrimmedString;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use ValueError;

class Validator
{
    use LoggerTrait;

    /** @var TypeDataValidatorInterface[] */
    private array $parametersValidators = [];

    /**
     * @param ReadAccRepositoryInterface $readAccRepository
     * @param ContactInterface $user
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository
     * @param \Traversable<TypeDataValidatorInterface> $parametersValidators
     */
    public function __construct(
        private readonly ReadAccRepositoryInterface $readAccRepository,
        private readonly ContactInterface $user,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository,
        \Traversable $parametersValidators,
    ) {
        $this->parametersValidators = iterator_to_array($parametersValidators);
    }

    /**
     * @param UpdateAccRequest $request
     * @param Acc $acc
     *
     * @throws AccException|ValueError
     */
    public function validateRequestOrFail(UpdateAccRequest $request, Acc $acc): void
    {
        $this->validateNameOrFail($request, $acc);
        $this->validateTypeOrFail($request, $acc);
        $this->validatePollersOrFail($request, $acc);
        $this->validateParametersOrFail($request);
    }

    /**
     * Validate that ACC name is not already used.
     *
     * @param UpdateAccRequest $request
     * @param Acc $acc
     *
     * @throws AccException
     */
    public function validateNameOrFail(UpdateAccRequest $request, Acc $acc): void
    {
        $trimmedName = new TrimmedString($request->name);

        if ($acc->getName() !== $trimmedName->value && $this->readAccRepository->existsByName($trimmedName)) {
            throw AccException::nameAlreadyExists($trimmedName->value);
        }
    }

    /**
     * Check that pollers are not already linked to same ACC type.
     *
     * @param UpdateAccRequest $request
     * @param Acc $acc
     *
     * @throws AccException|ValueError
     */
    public function validateTypeOrFail(UpdateAccRequest $request, Acc $acc): void
    {
        $type = Type::from($request->type);

        if ($type->name !== $acc->getType()->name) {
            throw AccException::typeChangeNotAllowed();
        }
    }

    /**
     * Validate that requesting user has access to pollers.
     *
     * @param UpdateAccRequest $request
     * @param Acc $acc
     *
     * @throws AccException
     */
    public function validatePollersOrFail(UpdateAccRequest $request, Acc $acc): void
    {
        if ([] === $request->pollers) {
            throw AccException::arrayCanNotBeEmpty('pollers');
        }

        // Check pollers have valid IDs according to user permissions.
        $invalidPollers = [];
        foreach ($request->pollers as $pollerId) {
            $isPollerIdValid = false;
            if ($this->user->isAdmin()) {
                $isPollerIdValid = $this->readMonitoringServerRepository->exists($pollerId);
            } else {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $isPollerIdValid = $this->readMonitoringServerRepository->existsByAccessGroups($pollerId, $accessGroups);
            }

            if (false === $isPollerIdValid) {
                $invalidPollers[] = $pollerId;
            }
        }

        if ($invalidPollers !== []) {
            throw AccException::idsDoNotExist('pollers', $invalidPollers);
        }

        // Check pollers are not already associated to an ACC of same type.
        $actualPollers = $this->readAccRepository->findPollersByAccId($acc->getId());
        $actualPollerIds = array_map(fn(Poller $poller) => $poller->id, $actualPollers);

        $unavailablePollers = $this->readAccRepository->findPollersByType($acc->getType());
        $unavailablePollerIds = array_map(fn(Poller $poller) => $poller->id, $unavailablePollers);
        $unavailablePollerIds = array_diff($unavailablePollerIds, $actualPollerIds);

        if ([] !== $invalidPollers = array_intersect($unavailablePollerIds, $request->pollers)) {
            throw AccException::alreadyAssociatedPollers($acc->getType(), $invalidPollers);
        }
    }

    public function validateParametersOrFail(UpdateAccRequest $request): void
    {
        foreach ($this->parametersValidators as $validator) {
            if ($validator->isValidFor(Type::from($request->type))) {
                $validator->validateParametersOrFail($request);
            }
        }
    }
}
