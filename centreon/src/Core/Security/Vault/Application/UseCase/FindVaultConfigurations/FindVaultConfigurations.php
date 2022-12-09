<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Core\Security\Vault\Application\UseCase\FindVaultConfigurations;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\{ErrorResponse, NotFoundResponse, ForbiddenResponse, PresenterInterface};
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Core\Security\Vault\Application\Exceptions\VaultConfigurationException;
use Core\Security\Vault\Application\Repository\{
    ReadVaultRepositoryInterface,
    ReadVaultConfigurationRepositoryInterface
};

final class FindVaultConfigurations
{
    use LoggerTrait;

    /**
     * @param ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository
     * @param ReadVaultRepositoryInterface $readVaultRepository
     * @param ContactInterface $user
     */
    public function __construct(
        readonly private ReadVaultConfigurationRepositoryInterface $readVaultConfigurationRepository,
        readonly private ReadVaultRepositoryInterface $readVaultRepository,
        readonly private ContactInterface $user
    ) {
    }

    /**
     * @param PresenterInterface $presenter
     * @param integer $vaultId
     */
    public function __invoke(
        PresenterInterface $presenter,
        int $vaultId
    ): void {
        try {
            if (! $this->user->isAdmin()) {
                $this->error('User is not admin', ['user' => $this->user->getName()]);
                $presenter->setResponseStatus(
                    new ForbiddenResponse(VaultConfigurationException::onlyForAdmin()->getMessage())
                );

                return;
            }

            if (! $this->readVaultRepository->exists($vaultId)) {
                $this->error('Vault provider not found', ['id' => $vaultId]);
                $presenter->setResponseStatus(
                    new NotFoundResponse('Vault provider')
                );

                return;
            }

            $vaultConfigurations = $this->readVaultConfigurationRepository->findVaultConfigurationsByVault($vaultId);

            $presenter->present($this->createResponse($vaultConfigurations));
        } catch (\Throwable $ex) {
            $this->error(
                'An error occured in while getting vault configurations',
                ['trace' => $ex->getTraceAsString()]
            );
            $presenter->setResponseStatus(
                new ErrorResponse(VaultConfigurationException::impossibleToFind()->getMessage())
            );
        }
    }

    /**
     * @param VaultConfiguration[] $vaultConfigurations
     *
     * @return FindVaultConfigurationsResponse
     */
    private function createResponse(array $vaultConfigurations): FindVaultConfigurationsResponse
    {
        $findVaultConfigurationsResponse = new FindVaultConfigurationsResponse();
        $findVaultConfigurationsResponse->vaultConfigurations = array_map(
            static fn (VaultConfiguration $vaultConfiguration): array => [
                'id' => $vaultConfiguration->getId(),
                'name' => $vaultConfiguration->getName(),
                'vault_id' => $vaultConfiguration->getVault()->getId(),
                'url' => $vaultConfiguration->getAddress(),
                'port' => $vaultConfiguration->getPort(),
                'storage' => $vaultConfiguration->getStorage()
            ],
            $vaultConfigurations
        );

        return $findVaultConfigurationsResponse;
    }
}
