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

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\AdditionalConnectorConfiguration\Application\Exception\AccException;
use Core\AdditionalConnectorConfiguration\Application\Factory\AccFactory;
use Core\AdditionalConnectorConfiguration\Application\Repository\ReadAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Application\Repository\WriteAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Application\Repository\WriteVaultAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\NewAcc;
use Core\AdditionalConnectorConfiguration\Domain\Model\Poller;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Common\Infrastructure\FeatureFlags;

final class AddAcc
{
    use LoggerTrait;

    /** @var WriteVaultAccRepositoryInterface[] */
    private array $writeVaultAccRepositories = [];

    /**
     * @param ReadAccRepositoryInterface $readAccRepository
     * @param WriteAccRepositoryInterface $writeAccRepository
     * @param Validator $validator
     * @param AccFactory $factory
     * @param DataStorageEngineInterface $dataStorageEngine
     * @param ContactInterface $user
     * @param FeatureFlags $flags
     * @param \Traversable<WriteVaultAccRepositoryInterface> $writeVaultAccRepositories
     */
    public function __construct(
        private readonly ReadAccRepositoryInterface $readAccRepository,
        private readonly WriteAccRepositoryInterface $writeAccRepository,
        private readonly Validator $validator,
        private readonly AccFactory $factory,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly ContactInterface $user,
        private readonly FeatureFlags $flags,
        \Traversable $writeVaultAccRepositories,
    ) {
        $this->writeVaultAccRepositories = iterator_to_array($writeVaultAccRepositories);
    }

    public function __invoke(
        AddAccRequest $request,
        AddAccPresenterInterface $presenter
    ): void {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_ACC_RW)) {
                $this->error(
                    "User doesn't have sufficient rights to access additional connector configurations",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(AccException::accessNotAllowed())
                );

                return;
            }
            $request->pollers = array_unique($request->pollers);

            $this->validator->validateRequestOrFail($request);

            $newAcc = $this->factory->createNewAcc(
                name: $request->name,
                type: Type::from($request->type),
                createdBy: $this->user->getId(),
                parameters: $request->parameters,
                description: $request->description,
            );

            if ($this->flags->isEnabled('vault_gorgone')) {
                $parameters = $newAcc->getParameters();

                foreach ($this->writeVaultAccRepositories as $repository) {
                    if ($repository->isValidFor($newAcc->getType())) {
                        $parameters = $repository->saveCredentialInVault($newAcc->getParameters());
                    }
                }

                $newAcc = $this->factory->createNewAcc(
                    name: $newAcc->getName(),
                    type: $newAcc->getType(),
                    createdBy: $newAcc->getCreatedBy(),
                    parameters: $parameters->getData(),
                    description: $newAcc->getDescription(),
                );
            }

            $accId = $this->addAcc($newAcc, $request->pollers);

            if (null === $additionalConnector = $this->readAccRepository->find($accId)) {
                throw AccException::errorWhileRetrievingObject();
            }

            $pollers = $this->readAccRepository->findPollersByAccId($accId);

            $presenter->presentResponse($this->createResponse($additionalConnector, $pollers));
        } catch (AssertionFailedException|\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse(
                $ex instanceof AccException
                    ? $ex
                    : AccException::addAcc()
            ));
        }
    }

    /**
     * @param NewAcc $acc
     * @param int[] $pollers
     *
     * @throws \Throwable
     *
     * @return int
     */
    private function addAcc(NewAcc $acc, array $pollers): int
    {
        try {
            $this->dataStorageEngine->startTransaction();

            $newAccId = $this->writeAccRepository->add($acc);
            $this->writeAccRepository->linkToPollers($newAccId, $pollers);

            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'AddAcc' transaction.");
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }

        return $newAccId;
    }

    /**
     * @param Acc $acc
     * @param Poller[] $pollers
     *
     * @return AddAccResponse
     */
    private function createResponse(Acc $acc, array $pollers): AddAccResponse
    {
        return new AddAccResponse(
            id: $acc->getId(),
            type: $acc->getType(),
            name: $acc->getName(),
            description: $acc->getDescription(),
            createdBy: ['id' => $this->user->getId(), 'name' => $this->user->getName()],
            updatedBy: ['id' => $this->user->getId(), 'name' => $this->user->getName()],
            createdAt: $acc->getCreatedAt(),
            updatedAt: $acc->getCreatedAt(),
            parameters: $acc->getParameters()->getDataWithoutCredentials(),
            pollers: $pollers
        );
    }
}
