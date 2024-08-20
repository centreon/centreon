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

namespace Core\Host\Application\UseCase\DeleteHost;

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
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Application\Repository\WriteHostRepositoryInterface;
use Core\Host\Domain\Model\Host;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\Macro\Application\Repository\ReadServiceMacroRepositoryInterface;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;

final class DeleteHost
{
    use LoggerTrait,VaultTrait;

    public function __construct(
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly WriteHostRepositoryInterface $writeHostRepository,
        private readonly ReadServiceRepositoryInterface $readServiceRepository,
        private readonly WriteServiceRepositoryInterface $writeServiceRepository,
        private readonly ContactInterface $contact,
        private readonly DataStorageEngineInterface $storageEngine,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly WriteMonitoringServerRepositoryInterface $writeMonitoringServerRepository,
        private readonly WriteVaultRepositoryInterface $writeVaultRepository,
        private readonly ReadHostMacroRepositoryInterface $readHostMacroRepository,
        private readonly ReadServiceMacroRepositoryInterface $readServiceMacroRepository,
    ) {
    }

    /**
     * @param int $hostId
     * @param PresenterInterface $presenter
     */
    public function __invoke(int $hostId, PresenterInterface $presenter): void
    {
        try {
            $this->info('Use case: DeleteHost');
            if (! $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to delete a host",
                    ['user_id' => $this->contact->getId(), 'host_id' => $hostId]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(HostException::deleteNotAllowed())
                );

                return;
            }

            if (! $this->hostExists($hostId) || ($host = $this->readHostRepository->findById($hostId)) === null) {
                $this->error('Host not found', ['host_id' => $hostId]);
                $presenter->setResponseStatus(new NotFoundResponse('Host'));

                return;
            }
            $this->deleteHost($host);

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(HostException::errorWhileDeleting($ex)));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param Host $host
     *
     * @throws \Throwable
     */
    private function deleteHost(Host $host): void
    {
        $this->debug('Start transaction');

        $this->storageEngine->startTransaction();
        try {
            $serviceIds = $this->readServiceRepository->findServiceIdsLinkedToHostId($host->getId());
            if ($serviceIds !== []) {
                $this->info('Services to delete', ['user_id' => $this->contact->getId(), 'services' => $serviceIds]);
                if ($this->writeVaultRepository->isVaultConfigured()) {
                    $serviceUuids = $this->retrieveServiceUuidsFromVault($serviceIds);
                    $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::SERVICE_VAULT_PATH);
                    foreach ($serviceUuids as $serviceUuid) {
                        $this->writeVaultRepository->delete($serviceUuid);
                    }
                }

                $this->writeServiceRepository->deleteByIds(...$serviceIds);
            } else {
                $this->info('No services to delete', ['user_id' => $this->contact->getId()]);
            }
            $this->info('Host to delete', ['user_id' => $this->contact->getId(), 'host_id' => $host->getId()]);

            if ($this->writeVaultRepository->isVaultConfigured()) {
                $this->retrieveHostUuidFromVault($host);
                if ($this->uuid !== null) {
                    $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::HOST_VAULT_PATH);
                    $this->writeVaultRepository->delete($this->uuid);
                }
            }
            $this->writeHostRepository->deleteById($host->getId());
            $this->writeMonitoringServerRepository->notifyConfigurationChange($host->getMonitoringServerId());
            $this->debug('Commit transaction');
            $this->storageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->debug('Rollback transaction');
            $this->storageEngine->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * Indicates whether the host can be deleted according to ACLs.
     *
     * @param int $hostId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    private function hostExists(int $hostId): bool
    {
        if ($this->contact->isAdmin()) {
            $this->info('Admin user', ['user_id' => $this->contact->getId()]);

            return $this->readHostRepository->exists($hostId);
        }
        $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);
        $this->info(
            'Non-admin user',
            [
                'user_id' => $this->contact->getId(),
                'access_groups' => array_map(fn (AccessGroup $accessGroup) => $accessGroup->getId(), $accessGroups)],
        );

        return $this->readHostRepository->existsByAccessGroups($hostId, $accessGroups);
    }

    /**
     * @param Host $host
     *
     * @throws \Throwable
     */
    private function retrieveHostUuidFromVault(Host $host): void
    {
        $this->uuid = $this->getUuidFromPath($host->getSnmpCommunity());
        if (null === $this->uuid) {
            $macros = $this->readHostMacroRepository->findByHostId($host->getId());
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

    /**
     * @param int[] $serviceIds
     *
     * @throws \Throwable
     *
     * @return string[]
     */
    private function retrieveServiceUuidsFromVault(array $serviceIds): array
    {
        $uuids = [];
        $macros = $this->readServiceMacroRepository->findByServiceIds(...$serviceIds);
        foreach ($macros as $macro) {
            if (
                $macro->isPassword() === true
                && null !== ($uuid = $this->getUuidFromPath($macro->getValue()))
            ) {
                $uuids[] = $uuid;
            }
        }

        return array_unique($uuids);
    }
}
