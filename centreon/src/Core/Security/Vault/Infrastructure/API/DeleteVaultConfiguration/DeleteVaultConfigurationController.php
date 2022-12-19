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

namespace Core\Security\Vault\Infrastructure\API\DeleteVaultConfiguration;

use Centreon\Application\Controller\AbstractController;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Security\Vault\Application\UseCase\DeleteVaultConfiguration\{
    DeleteVaultConfiguration,
    DeleteVaultConfigurationRequest
};

final class DeleteVaultConfigurationController extends AbstractController
{
    /**
     * @param integer $vaultId
     * @param integer $vaultConfigurationId
     * @param DeleteVaultConfiguration $useCase
     * @param DefaultPresenter $presenter
     *
     * @return object
     */
    public function __invoke(
        int $vaultId,
        int $vaultConfigurationId,
        DeleteVaultConfiguration $useCase,
        DefaultPresenter $presenter
    ): object {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $deleteVaultConfigurationRequest = $this->createDtoRequest(
            $vaultId,
            $vaultConfigurationId
        );

        $useCase($presenter, $deleteVaultConfigurationRequest);

        return $presenter->show();
    }

    /**
     * @param int $vaultId
     * @param int $vaultConfigurationId
     *
     * @return DeleteVaultConfigurationRequest
     */
    private function createDtoRequest(
        int $vaultId,
        int $vaultConfigurationId
    ): DeleteVaultConfigurationRequest {
        $deleteVaultConfigurationRequest = new DeleteVaultConfigurationRequest();
        $deleteVaultConfigurationRequest->vaultConfigurationId = $vaultConfigurationId;
        $deleteVaultConfigurationRequest->typeId = $vaultId;

        return $deleteVaultConfigurationRequest;
    }
}
