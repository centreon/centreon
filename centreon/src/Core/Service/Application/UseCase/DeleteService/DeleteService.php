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

namespace Core\Service\Application\UseCase\DeleteService;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Application\UseCase\VaultTrait;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Service\Application\Exception\ServiceException;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;

final class DeleteService
{
    use LoggerTrait,VaultTrait;

    /**
     * @param ReadServiceRepositoryInterface $readRepository
     * @param WriteServiceRepositoryInterface $writeRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param ContactInterface $user
     * @param WriteMonitoringServerRepositoryInterface $writeMonitoringServerRepository
     * @param DataStorageEngineInterface $storageEngine
     * @param WriteVaultRepositoryInterface $writeVaultRepository
     * @param ReadServiceMacroRepositoryInterface $readServiceMacroRepository
     */
    public function __construct(
        private readonly ReadServiceRepositoryInterface $readRepository,
        private readonly WriteServiceRepositoryInterface $writeRepository,
        private readonly WriteMonitoringServerRepositoryInterface $writeMonitoringServerRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly DataStorageEngineInterface $storageEngine,
        private readonly ContactInterface $user,
        private readonly WriteVaultRepositoryInterface $writeVaultRepository,
        private readonly ReadServiceMacroRepositoryInterface $readServiceMacroRepository,
    ) {
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
    }

    /**
     * @param int $serviceId
     * @param PresenterInterface $presenter
     */
    public function __invoke(int $serviceId, PresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to delete a service",
                    ['user_id' => $this->user->getId(), 'service_id' => $serviceId]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceException::deleteNotAllowed())
                );

                return;
            }

            if ($this->user->isAdmin()) {
                $doesServiceExists = $this->readRepository->exists($serviceId);
            } else {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $doesServiceExists = $this->readRepository->existsByAccessGroups($serviceId, $accessGroups);
            }

            if ($doesServiceExists === false) {
                $this->error('Service not found', ['service_id' => $serviceId]);
                $presenter->setResponseStatus(new NotFoundResponse('Service'));

                return;
            }

            try {
                $this->storageEngine->startTransaction();

                $monitoringServerId = $this->readRepository->findMonitoringServerId($serviceId);
                $this->info("Delete service #{$serviceId}");

                if ($this->writeVaultRepository->isVaultConfigured()) {
                    $this->retrieveServiceUuidFromVault($serviceId);
                    if ($this->uuid !== null) {
                        $this->writeVaultRepository->delete($this->uuid);
                    }
                }
                $this->writeRepository->delete($serviceId);
                $this->info("Notify monitoiring server #{$monitoringServerId} of configuration change");
                $this->writeMonitoringServerRepository->notifyConfigurationChange($monitoringServerId);

                $this->storageEngine->commitTransaction();
            } catch (\Throwable $ex) {
                $this->error("Rollback of 'Delete Service' transaction.");
                $this->storageEngine->rollbackTransaction();

                throw $ex;
            }
            $presenter->setResponseStatus(new NoContentResponse());
            $this->info(
                'Service deleted',
                [
                    'service_id' => $serviceId,
                    'user_id' => $this->user->getId(),
                ]
            );
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(ServiceException::errorWhileDeleting($ex)));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param int $serviceId
     *
     * @throws \Throwable
     */
    private function retrieveServiceUuidFromVault(int $serviceId): void
    {
        $macros = $this->readServiceMacroRepository->findByServiceIds($serviceId);
        foreach ($macros as $macro) {
            if (
                $macro->isPassword() === true
                && null !== ($this->uuid = $this->getUuidFromPath($macro->getValue()))
            ) {
                break;
            }
        }
    }
}
