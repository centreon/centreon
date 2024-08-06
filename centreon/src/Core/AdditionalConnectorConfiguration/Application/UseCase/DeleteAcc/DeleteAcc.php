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

namespace Core\AdditionalConnectorConfiguration\Application\UseCase\DeleteAcc;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\AdditionalConnectorConfiguration\Application\Exception\AccException;
use Core\AdditionalConnectorConfiguration\Application\Repository\ReadAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Application\Repository\WriteAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Application\Repository\WriteVaultAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Poller;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Common\Infrastructure\FeatureFlags;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class DeleteAcc
{
    use LoggerTrait;

    /** @var WriteVaultAccRepositoryInterface[] */
    private array $writeVaultAccRepositories = [];

    /**
     * @param ReadAccRepositoryInterface $readAccRepository
     * @param WriteAccRepositoryInterface $writeAccRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository
     * @param ContactInterface $user
     * @param FeatureFlags $flags
     * @param \Traversable<WriteVaultAccRepositoryInterface> $writeVaultAccRepositories
     */
    public function __construct(
        private readonly ReadAccRepositoryInterface $readAccRepository,
        private readonly WriteAccRepositoryInterface $writeAccRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository,
        private readonly ContactInterface $user,
        private readonly FeatureFlags $flags,
        \Traversable $writeVaultAccRepositories,
    ) {
        $this->writeVaultAccRepositories = iterator_to_array($writeVaultAccRepositories);
    }

    public function __invoke(
        int $id,
        PresenterInterface $presenter
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

            if (null === $acc = $this->readAccRepository->find($id)) {
                $presenter->setResponseStatus(new NotFoundResponse('Additional Connector Configuration'));

                return;
            }

            if (false === $this->user->isAdmin()) {
                $linkedPollerIds = array_map(
                    static fn(Poller $poller): int => $poller->id,
                    $this->readAccRepository->findPollersByAccId($id)
                );

                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $accessiblePollerIds = $this->readMonitoringServerRepository->existByAccessGroups(
                    $linkedPollerIds,
                    $accessGroups
                );
                if (array_diff($linkedPollerIds, $accessiblePollerIds)) {
                    $presenter->setResponseStatus(
                        new ForbiddenResponse(AccException::unsufficientRights())
                    );

                    return;
                }
            }

            if ($this->flags->isEnabled('vault_gorgone')) {
                foreach ($this->writeVaultAccRepositories as $repository) {
                    if ($repository->isValidFor($acc->getType())) {
                        $repository->deleteFromVault($acc);
                    }
                }
            }

            $this->writeAccRepository->delete($id);

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse(
                $ex instanceof AccException
                    ? $ex
                    : AccException::deleteAcc()
            ));
        }
    }
}
