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

namespace Core\Security\Vault\Infrastructure\API\CreateVaultConfiguration;

use Centreon\Application\Controller\AbstractController;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Security\Vault\Application\UseCase\CreateVaultConfiguration\{
    CreateVaultConfiguration,
    CreateVaultConfigurationRequest
};
use Symfony\Component\HttpFoundation\Request;

final class CreateVaultConfigurationController extends AbstractController
{
    /**
     * @param integer $vaultId
     * @param CreateVaultConfiguration $useCase
     * @param DefaultPresenter $presenter
     * @param Request $request
     *
     * @return object
     */
    public function __invoke(
        int $vaultId,
        CreateVaultConfiguration $useCase,
        DefaultPresenter $presenter,
        Request $request
    ): object {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        /**
         * @var array{
         *  "name": string,
         *  "address": string,
         *  "port": integer,
         *  "root_path": string,
         *  "role_id": string,
         *  "secret_id": string
         * } $decodedRequest
         */
        $decodedRequest = $this->validateAndRetrieveDataSent(
            $request,
            __DIR__ . '/CreateVaultConfigurationSchema.json'
        );

        $createVaultConfigurationRequest = $this->createDtoRequest($vaultId, $decodedRequest);

        $useCase($presenter, $createVaultConfigurationRequest);

        return $presenter->show();
    }

    /**
     * @param int $vaultId
     * @param array{
     *  "name": string,
     *  "address": string,
     *  "port": integer,
     *  "root_path": string,
     *  "role_id": string,
     *  "secret_id": string
     * } $decodedRequest
     *
     * @return CreateVaultConfigurationRequest
     */
    private function createDtoRequest(
        int $vaultId,
        array $decodedRequest
    ): CreateVaultConfigurationRequest {
        $createVaultConfigurationRequest = new CreateVaultConfigurationRequest();
        $createVaultConfigurationRequest->name = $decodedRequest['name'];
        $createVaultConfigurationRequest->typeId = $vaultId;
        $createVaultConfigurationRequest->address = $decodedRequest['address'];
        $createVaultConfigurationRequest->port = $decodedRequest['port'];
        $createVaultConfigurationRequest->rootPath = $decodedRequest['root_path'];
        $createVaultConfigurationRequest->roleId = $decodedRequest['role_id'];
        $createVaultConfigurationRequest->secretId = $decodedRequest['secret_id'];

        return $createVaultConfigurationRequest;
    }
}
