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

namespace Core\Security\ProviderConfiguration\Application\UseCase\FindProviderConfigurations;

use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\OpenId\Exceptions\OpenIdConfigurationException;

interface ProviderConfigurationDtoFactoryInterface
{
    /**
     * Validate Factory is valid for provider type.
     *
     * @param string $type
     *
     * @return bool
     */
    public function supports(string $type): bool;

    /**
     * @param Configuration $configuration
     *
     * @throws OpenIdConfigurationException
     * @throws \Throwable
     *
     * @return ProviderConfigurationDto
     */
    public function createResponse(Configuration $configuration): ProviderConfigurationDto;
}
