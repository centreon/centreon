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

namespace Core\AdditionalConnector\Application\UseCase\DeleteAdditionalConnector;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\AdditionalConnector\Application\Exception\AdditionalConnectorException;
use Core\AdditionalConnector\Application\Repository\ReadAdditionalConnectorRepositoryInterface;
use Core\AdditionalConnector\Application\Repository\WriteAdditionalConnectorRepositoryInterface;
use Core\AdditionalConnector\Application\Repository\WriteVaultAdditionalConnectorRepositoryInterface;
use Core\AdditionalConnector\Domain\Model\Poller;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Common\Infrastructure\FeatureFlags;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class DeleteAdditionalConnector
{
    use LoggerTrait;

    /** @var WriteVaultAdditionalConnectorRepositoryInterface[] */
    private array $writeVaultAccRepositories = [];

    /**
     * @param ReadAdditionalConnectorRepositoryInterface $readAdditionalConnectorRepository
     * @param WriteAdditionalConnectorRepositoryInterface $writeAdditionalConnectorRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository
     * @param ContactInterface $user
     * @param FeatureFlags $flags
     * @param \Traversable<WriteVaultAdditionalConnectorRepositoryInterface> $writeVaultAccRepositories
     */
    public function __construct(
        private readonly ReadAdditionalConnectorRepositoryInterface $readAdditionalConnectorRepository,
        private readonly WriteAdditionalConnectorRepositoryInterface $writeAdditionalConnectorRepository,
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
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_ADDITIONAL_CONNECTOR_CONFIGURATION_RW)) {
                $this->error(
                    "User doesn't have sufficient rights to delete additional connector configurations",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(AdditionalConnectorException::deleteNotAllowed())
                );

                return;
            }

            if (null === $additionalConnector = $this->readAdditionalConnectorRepository->find($id)) {
                $presenter->setResponseStatus(new NotFoundResponse('Additional Connector'));

                return;
            }

            if (false === $this->user->isAdmin()) {
                $linkedPollerIds = array_map(
                    static fn(Poller $poller): int => $poller->id,
                    $this->readAdditionalConnectorRepository->findPollersByAccId($id)
                );

                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $accessiblePollerIds = $this->readMonitoringServerRepository->existByAccessGroups(
                    $linkedPollerIds,
                    $accessGroups
                );
                if (array_diff($linkedPollerIds, $accessiblePollerIds)) {
                    $presenter->setResponseStatus(
                        new ForbiddenResponse(AdditionalConnectorException::unsufficientRights())
                    );

                    return;
                }
            }

            if ($this->flags->isEnabled('vault_gorgone')) {
                foreach ($this->writeVaultAccRepositories as $repository) {
                    if ($repository->isValidFor($additionalConnector->getType())) {
                        $repository->deleteFromVault($additionalConnector);
                    }
                }
            }

            $this->writeAdditionalConnectorRepository->delete($id);

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse(
                $ex instanceof AdditionalConnectorException
                    ? $ex
                    : AdditionalConnectorException::deleteAdditionalConnector()
            ));
        }
    }
}
