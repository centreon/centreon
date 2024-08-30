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

namespace Core\AdditionalConnectorConfiguration\Application\UseCase\AddAcc;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\AdditionalConnectorConfiguration\Application\Exception\AccException;
use Core\AdditionalConnectorConfiguration\Application\Repository\ReadAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Application\Validation\TypeDataValidatorInterface;
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
     * @param AddAccRequest $request
     *
     * @throws AccException|ValueError
     */
    public function validateRequestOrFail(AddAccRequest $request): void
    {
        $this->validateNameOrFail($request);
        $this->validatePollersOrFail($request);
        $this->validateTypeOrFail($request);
        $this->validateParametersOrFail($request);
    }

    /**
     * Validate that ACC name is not already used.
     *
     * @param AddAccRequest $request
     *
     * @throws AccException
     */
    public function validateNameOrFail(AddAccRequest $request): void
    {
        $trimmedName = new TrimmedString($request->name);
        if ($this->readAccRepository->existsByName($trimmedName)) {
            throw AccException::nameAlreadyExists($trimmedName->value);
        }
    }

    /**
     * Validate that requesting user has access to pollers.
     *
     * @param AddAccRequest $request
     *
     * @throws AccException
     */
    public function validatePollersOrFail(AddAccRequest $request): void
    {
        if ([] === $request->pollers) {
            throw AccException::arrayCanNotBeEmpty('pollers');
        }

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
    }

    /**
     * Check that pollers are not already linked to same ACC type.
     *
     * @param AddAccRequest $request
     *
     * @throws AccException|ValueError
     */
    public function validateTypeOrFail(AddAccRequest $request): void
    {
        $type = Type::from($request->type);

        $pollers = $this->readAccRepository->findPollersByType($type);
        $pollerIds = array_map(fn(Poller $poller) => $poller->id, $pollers);

        if ([] !== $invalidPollers = array_intersect($pollerIds, $request->pollers)) {
            throw AccException::alreadyAssociatedPollers($type, $invalidPollers);
        }
    }

    public function validateParametersOrFail(AddAccRequest $request): void
    {
        foreach ($this->parametersValidators as $validator) {
            if ($validator->isValidFor(Type::from($request->type))) {
                $validator->validateParametersOrFail($request);
            }
        }
    }
}
