<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\AdditionalConnectorConfiguration\Application\Exception\AccException;
use Core\AdditionalConnectorConfiguration\Application\Factory\AccFactory;
use Core\AdditionalConnectorConfiguration\Application\Repository\ReadAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Application\Repository\ReadVaultAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Application\Repository\WriteAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Application\Repository\WriteVaultAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\Poller;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Common\Application\VaultEligibilityService;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class UpdateAcc
{
    use LoggerTrait;

    /** @var WriteVaultAccRepositoryInterface[] */
    private array $writeVaultAccRepositories = [];

    /** @var ReadVaultAccRepositoryInterface[] */
    private array $readVaultAccRepositories = [];

    /**
     * @param ReadAccRepositoryInterface $readAccRepository
     * @param WriteAccRepositoryInterface $writeAccRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
     * @param ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository,
     * @param Validator $validator
     * @param AccFactory $factory
     * @param DataStorageEngineInterface $dataStorageEngine
     * @param ContactInterface $user
     * @param VaultEligibilityService $vaultEligibilityService
     * @param \Traversable<WriteVaultAccRepositoryInterface> $writeVaultAccRepositories
     * @param \Traversable<ReadVaultAccRepositoryInterface> $readVaultAccRepositories
     * @param WriteMonitoringServerRepositoryInterface $writeMonitoringServerRepository
     */
    public function __construct(
        private readonly ReadAccRepositoryInterface $readAccRepository,
        private readonly WriteAccRepositoryInterface $writeAccRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository,
        private readonly Validator $validator,
        private readonly AccFactory $factory,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly ContactInterface $user,
        private readonly VaultEligibilityService $vaultEligibilityService,
        \Traversable $writeVaultAccRepositories,
        \Traversable $readVaultAccRepositories,
        private readonly WriteMonitoringServerRepositoryInterface $writeMonitoringServerRepository,
    ) {
        $this->writeVaultAccRepositories = iterator_to_array($writeVaultAccRepositories);
        $this->readVaultAccRepositories = iterator_to_array($readVaultAccRepositories);
    }

    public function __invoke(
        UpdateAccRequest $request,
        PresenterInterface $presenter,
    ): void {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_ACC_RW)) {
                $this->error(
                    "User doesn't have sufficient rights to access additional connector configurations",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(AccException::accessNotAllowed())
                );

                return;
            }

            if (null === $acc = $this->getAcc($request->id)) {
                $presenter->setResponseStatus(
                    new NotFoundResponse('Additional Connector Configuration')
                );

                return;
            }

            $request->pollers = array_unique($request->pollers);

            $this->validator->validateRequestOrFail($request, $acc);

            $decryptedPreviousAcc = $this->retrieveCredentials($acc);
            $updatedAcc = $this->factory->updateAcc(
                acc: $decryptedPreviousAcc,
                name: $request->name,
                updatedBy: $this->user->getId(),
                description: $request->description,
                parameters: $request->parameters
            );

            $parametersChanged = $decryptedPreviousAcc->getParameters()->getDecryptedData()
                !== $updatedAcc->getParameters()->getDecryptedData();

            if ($this->vaultEligibilityService->shouldUseVault('vault_gorgone')) {
                $parameters = $updatedAcc->getParameters();

                foreach ($this->writeVaultAccRepositories as $repository) {
                    if ($repository->isValidFor($updatedAcc->getType())) {
                        $repository->deleteFromVault($acc);
                        $parameters = $repository->saveCredentialInVault($updatedAcc->getParameters());
                    }
                }

                $vaultedAcc = $this->factory->createAcc(
                    id: $updatedAcc->getId(),
                    name: $updatedAcc->getName(),
                    type: $updatedAcc->getType(),
                    createdBy: $updatedAcc->getCreatedBy(),
                    createdAt: $updatedAcc->getCreatedAt(),
                    updatedBy: $updatedAcc->getUpdatedBy(),
                    updatedAt: $updatedAcc->getUpdatedAt(),
                    description: $updatedAcc->getDescription(),
                    parameters: $parameters->getData(),
                );

                $updatedAcc = $vaultedAcc;
            }

            $this->update($updatedAcc, $request->pollers, $parametersChanged);

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (AssertionFailedException|\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse(
                $ex instanceof AccException
                    ? $ex
                    : AccException::updateAcc()
            ));
        }
    }

    /**
     * Get ACC based on user rights.
     *
     * @param int $id
     *
     * @throws \Throwable
     *
     * @return null|Acc
     */
    private function getAcc(int $id): null|Acc
    {
        if (null === $acc = $this->readAccRepository->find($id)) {

            return null;
        }

        if (! $this->user->isAdmin()) {
            $pollerIds = array_map(
                static fn (Poller $poller): int => $poller->id,
                $this->readAccRepository->findPollersByAccId($acc->getId())
            );
            $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
            $validPollerIds = $this->readMonitoringServerRepository->existByAccessGroups($pollerIds, $accessGroups);

            if (array_diff($pollerIds, $validPollerIds) !== []) {

                return null;
            }
        }

        return $acc;
    }

    /**
     * @param Acc $acc
     *
     * @throws \Throwable
     *
     * @return Acc
     */
    private function retrieveCredentials(Acc $acc): Acc
    {
        $parameters = $acc->getParameters();
        foreach ($this->readVaultAccRepositories as $repository) {
            if ($repository->isValidFor($acc->getType())) {
                $parameters = $repository->getCredentialsFromVault($acc->getParameters());
            }
        }

        return $this->factory->createAcc(
            id: $acc->getId(),
            name: $acc->getName(),
            type: $acc->getType(),
            createdBy: $acc->getCreatedBy(),
            createdAt: $acc->getCreatedAt(),
            updatedBy: $acc->getUpdatedBy(),
            updatedAt: $acc->getUpdatedAt(),
            parameters: $parameters->getDecryptedData(),
            description: $acc->getDescription(),
        );
    }

    /**
     * @param Acc $acc
     * @param int[] $pollers
     * @param bool $parametersChanged
     *
     * @throws \Throwable
     */
    private function update(Acc $acc, array $pollers, bool $parametersChanged): void
    {
        try {
            $this->dataStorageEngine->startTransaction();

            // Get previous pollers before re-linking (they may need VMWare restart too if removed)
            $previousPollerIds = array_map(
                static fn (Poller $poller): int => $poller->id,
                $this->readAccRepository->findPollersByAccId($acc->getId())
            );

            $this->writeAccRepository->update($acc);
            $this->writeAccRepository->removePollers($acc->getId());
            $this->writeAccRepository->linkToPollers(
                $acc->getId(),
                $pollers
            );

            // Pollers needing a VMWare restart:
            //  - if the parameters changed: every poller that had or now has the ACC linked
            //  - otherwise: only pollers that just gained or lost the link
            $pollersToNotify = $parametersChanged
                ? array_values(array_unique(array_merge($previousPollerIds, $pollers)))
                : array_values(array_unique(array_merge(
                    array_diff($pollers, $previousPollerIds),
                    array_diff($previousPollerIds, $pollers)
                )));

            if ($pollersToNotify !== []) {
                $this->writeMonitoringServerRepository->notifyVmwareConfigurationChange(...$pollersToNotify);
            }

            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'UpdateAcc' transaction.");
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }
    }
}
