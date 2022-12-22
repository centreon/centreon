<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 *  For more information : contact@centreon.com
 */

declare(strict_types=1);

namespace Core\Security\ProviderConfiguration\Domain\SecurityAccess\AttributePath;

use Core\Security\Authentication\Domain\Model\ProviderToken;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Endpoint;

/**
 * All fetchers must implement this interface
 * A fetcher is associate to one Endpoint type: Introspection, user information or custom url
 * Fetchers are use in authentication to control access on OIDC and SAML
 * @see Endpoint
 */
interface AttributePathFetcherInterface
{
    /**
     * @param string $accessToken
     * @param Configuration $configuration
     * @param Endpoint $endpoint
     * @return array<string,mixed>
     */
    public function fetch(string $accessToken, Configuration $configuration, Endpoint $endpoint): array;

    /**
     * If the method supports return true the fetch method will be executed
     *
     * @param Endpoint $endpoint
     * @return bool
     */
    public function supports(Endpoint $endpoint): bool;
}
