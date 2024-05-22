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
     * @param UpdateVaultConfiguration $useCase
     * @param DefaultPresenter $presenter
     * @param Request $request
     *
     * @return object
     */
    public function __invoke(
        UpdateVaultConfiguration $useCase,
        DefaultPresenter $presenter,
        Request $request
    ): object {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        /**
         * @var array{
         *  "address": string,
         *  "port": integer,
         *  "role_id": string,
         *  "secret_id": string
         * } $decodedRequest
         */
        $decodedRequest = $this->validateAndRetrieveDataSent(
            $request,
            __DIR__ . '/UpdateVaultConfigurationSchema.json'
        );

        $updateVaultConfigurationRequest = $this->createDtoRequest(
            $decodedRequest
        );

        $useCase($presenter, $updateVaultConfigurationRequest);

        return $presenter->show();
    }

    /**
     * @param array{
     *  "address": string,
     *  "port": integer,
     *  "role_id": string,
     *  "secret_id": string
     * } $decodedRequest
     *
     * @return UpdateVaultConfigurationRequest
     */
    private function createDtoRequest(
        array $decodedRequest
    ): UpdateVaultConfigurationRequest {
        $updateVaultConfigurationRequest = new UpdateVaultConfigurationRequest();
        $updateVaultConfigurationRequest->address = $decodedRequest['address'];
        $updateVaultConfigurationRequest->port = $decodedRequest['port'];
        $updateVaultConfigurationRequest->roleId = $decodedRequest['role_id'];
        $updateVaultConfigurationRequest->secretId = $decodedRequest['secret_id'];

        return $updateVaultConfigurationRequest;
    }
}
