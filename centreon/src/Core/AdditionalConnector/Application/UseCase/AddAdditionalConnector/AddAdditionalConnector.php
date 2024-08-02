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

namespace Core\AdditionalConnector\Application\UseCase\AddAdditionalConnector;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\AdditionalConnector\Application\Exception\AdditionalConnectorException;
use Core\AdditionalConnector\Application\Repository\ReadAdditionalConnectorRepositoryInterface;
use Core\AdditionalConnector\Application\Repository\WriteAdditionalConnectorRepositoryInterface;
use Core\AdditionalConnector\Application\Repository\WriteVaultAdditionalConnectorRepositoryInterface;
use Core\AdditionalConnector\Application\UseCase\AddAdditionalConnector\Validation\Validator;
use Core\AdditionalConnector\Domain\Model\AdditionalConnector;
use Core\AdditionalConnector\Domain\Model\NewAdditionalConnector;
use Core\AdditionalConnector\Domain\Model\Poller;
use Core\AdditionalConnector\Domain\Model\Type;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Common\Infrastructure\FeatureFlags;

final class AddAdditionalConnector
{
    use LoggerTrait;

    /** @var WriteVaultAdditionalConnectorRepositoryInterface[] */
    private array $writeVaultAccRepositories = [];

    /**
     * @param ReadAdditionalConnectorRepositoryInterface $readAdditionalConnectorRepository
     * @param WriteAdditionalConnectorRepositoryInterface $writeAdditionalConnectorRepository
     * @param Validator $validator
     * @param DataStorageEngineInterface $dataStorageEngine
     * @param ContactInterface $user
     * @param FeatureFlags $flags
     * @param \Traversable<WriteVaultAdditionalConnectorRepositoryInterface> $writeVaultAccRepositories
     */
    public function __construct(
        private readonly ReadAdditionalConnectorRepositoryInterface $readAdditionalConnectorRepository,
        private readonly WriteAdditionalConnectorRepositoryInterface $writeAdditionalConnectorRepository,
        private readonly Validator $validator,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly ContactInterface $user,
        private readonly FeatureFlags $flags,
        \Traversable $writeVaultAccRepositories,
    ) {
        $this->writeVaultAccRepositories = iterator_to_array($writeVaultAccRepositories);
    }

    public function __invoke(
        AddAdditionalConnectorRequest $request,
        AddAdditionalConnectorPresenterInterface $presenter
    ): void {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_ADDITIONAL_CONNECTOR_RW)) {
                $this->error(
                    "User doesn't have sufficient rights to add additional connectors",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(AdditionalConnectorException::addNotAllowed())
                );

                return;
            }
            $request->pollers = array_unique($request->pollers);

            $this->validator->validateRequestOrFail($request);

            $newAdditionalConnector = $this->createAdditionalConnector($request);

            if ($this->flags->isEnabled('vault_gorgone')) {
                foreach ($this->writeVaultAccRepositories as $repository) {
                    if ($repository->isValidFor($newAdditionalConnector->getType())) {
                        $parameters = $repository->saveCredentialInVault($newAdditionalConnector->getParameters());
                    }
                }

                $newAdditionalConnector = new NewAdditionalConnector(
                    name: $newAdditionalConnector->getName(),
                    type: $newAdditionalConnector->getType(),
                    createdBy: $newAdditionalConnector->getCreatedBy(),
                    parameters: $parameters ?? $newAdditionalConnector->getParameters(),
                    description: $newAdditionalConnector->getDescription(),
                );
            }

            $accId = $this->addAdditionalConnector($newAdditionalConnector, $request->pollers);

            if (null === $additionalConnector = $this->readAdditionalConnectorRepository->find($accId)) {
                throw AdditionalConnectorException::errorWhileRetrievingObject();
            }

            $pollers = $this->readAdditionalConnectorRepository->findPollersByAccId($accId);

            $presenter->presentResponse($this->createResponse($additionalConnector, $pollers));
        } catch (AssertionFailedException|\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse(
                $ex instanceof AdditionalConnectorException
                    ? $ex
                    : AdditionalConnectorException::addAdditionalConnector()
            ));
        }
    }

    /**
     * @param AddAdditionalConnectorRequest $request
     *
     * @throws AssertionFailedException
     *
     * @return NewAdditionalConnector
     */
    private function createAdditionalConnector(AddAdditionalConnectorRequest $request): NewAdditionalConnector
    {
        return new NewAdditionalConnector(
            name: $request->name,
            type: Type::from($request->type),
            createdBy: $this->user->getId(),
            parameters: $request->parameters,
            description: $request->description,
        );
    }

    /**
     * @param NewAdditionalConnector $acc
     * @param int[] $pollers
     *
     * @throws \Throwable
     *
     * @return int
     */
    private function addAdditionalConnector(NewAdditionalConnector $acc, array $pollers): int
    {
        try {
            $this->dataStorageEngine->startTransaction();

            $newAccId = $this->writeAdditionalConnectorRepository->add($acc);
            $this->writeAdditionalConnectorRepository->linkToPollers(
                $newAccId,
                $pollers
            );

            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'AddAdditionalConnector' transaction.");
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }

        return $newAccId;
    }

    /**
     * @param AdditionalConnector $acc
     * @param Poller[] $pollers
     *
     * @return AddAdditionalConnectorResponse
     */
    private function createResponse(AdditionalConnector $acc, array $pollers): AddAdditionalConnectorResponse
    {
        return new AddAdditionalConnectorResponse(
            id: $acc->getId(),
            type: $acc->getType(),
            name: $acc->getName(),
            description: $acc->getDescription(),
            createdBy: ['id' => $this->user->getId(), 'name' => $this->user->getName()],
            updatedBy: ['id' => $this->user->getId(), 'name' => $this->user->getName()],
            createdAt: $acc->getCreatedAt(),
            updatedAt: $acc->getCreatedAt(),
            parameters: $acc->getParameters(),
            pollers: $pollers
        );
    }
}
