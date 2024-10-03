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

namespace Core\Security\ProviderConfiguration\Infrastructure\Api\FindProviderConfigurations;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations\{
    FindProviderConfigurationsPresenterInterface, FindProviderConfigurationsResponse, ProviderConfigurationDto};
class FindProviderConfigurationsPresenter extends AbstractPresenter implements FindProviderConfigurationsPresenterInterface
{
    /**
     * @inheritDoc
     */
    public function presentResponse(ResponseStatusInterface|FindProviderConfigurationsResponse $data): void
    {
        if ($data instanceof ResponseStatusInterface) {
            $this->setResponseStatus($data);
        } else {
            $this->present(array_map(
                function (ProviderConfigurationDto $dto): array {
                    return [
                        'id' => $dto->id,
                        'type' => $dto->type,
                        'name' => $dto->name,
                        'authentication_uri' => $dto->authenticationUri,
                        'is_active' => $dto->isActive,
                        'is_forced' => $dto->isForced,
                    ];
                },
                $data->providerConfigurations
            ));
        }
    }
}
