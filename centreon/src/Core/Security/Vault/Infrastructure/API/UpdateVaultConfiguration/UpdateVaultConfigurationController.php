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

namespace Core\Security\Vault\Infrastructure\API\UpdateVaultConfiguration;

use Centreon\Application\Controller\AbstractController;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Security\Vault\Application\UseCase\UpdateVaultConfiguration\{
    UpdateVaultConfiguration,
    UpdateVaultConfigurationRequest
};
use Symfony\Component\HttpFoundation\Request;

final class UpdateVaultConfigurationController extends AbstractController
{
    /**
     * @param integer $vaultId
     * @param integer $vaultConfigurationId
     * @param UpdateVaultConfiguration $useCase
     * @param DefaultPresenter $presenter
     * @param Request $request
     *
     * @return object
     */
    public function __invoke(
        int $vaultId,
        int $vaultConfigurationId,
        UpdateVaultConfiguration $useCase,
        DefaultPresenter $presenter,
        Request $request
    ): object {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        /**
         * @var array{
         *  "name": string,
         *  "address": string,
         *  "port": integer,
         *  "storage": string,
         *  "role_id": string,
         *  "secret_id": string
         * } $decodedRequest
         */
        $decodedRequest = $this->validateAndRetrieveDataSent(
            $request,
            __DIR__ . '/UpdateVaultConfigurationSchema.json'
        );

        $updateVaultConfigurationRequest = $this->createDtoRequest(
            $vaultId,
            $vaultConfigurationId,
            $decodedRequest
        );

        $useCase($presenter, $updateVaultConfigurationRequest);

        return $presenter->show();
    }

    /**
     * @param int $vaultId
     * @param int $vaultConfigurationId
     * @param array{
     *  "name": string,
     *  "address": string,
     *  "port": integer,
     *  "storage": string,
     *  "role_id": string,
     *  "secret_id": string
     * } $decodedRequest
     *
     * @return UpdateVaultConfigurationRequest
     */
    private function createDtoRequest(
        int $vaultId,
        int $vaultConfigurationId,
        array $decodedRequest
    ): UpdateVaultConfigurationRequest {
        $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();
        $updateVaultConfigurationRequest->vaultConfigurationId = $vaultConfigurationId;
        $updateVaultConfigurationRequest->name = $decodedRequest['name'];
        $updateVaultConfigurationRequest->typeId = $vaultId;
        $updateVaultConfigurationRequest->address = $decodedRequest['address'];
        $updateVaultConfigurationRequest->port = $decodedRequest['port'];
        $updateVaultConfigurationRequest->storage = $decodedRequest['storage'];
        $updateVaultConfigurationRequest->roleId = $decodedRequest['role_id'];
        $updateVaultConfigurationRequest->secretId = $decodedRequest['secret_id'];

        return $updateVaultConfigurationRequest;
    }
}
