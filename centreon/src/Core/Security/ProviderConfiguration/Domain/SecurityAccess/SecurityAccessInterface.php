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

namespace Core\Security\ProviderConfiguration\Domain\SecurityAccess;

use Core\Security\Authentication\Infrastructure\Provider\OpenId;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\SecurityAccess\AttributePath\AttributePathFetcherInterface;

/**
 * Interface used by each security access controls: Authentication conditions, Roles mapping and Groups mapping
 * Security access controls are use in authentication to control access on OIDC and SAML, you probably find theme in
 * OIDC provider and SAML provider classes
 *
 * <b>Important</b>: If all security access are enabled, they must all be satisfied to authorize the user
 *
 * @see Conditions (SecurityAccess)
 * @see RolesMapping (SecurityAccess)
 * @see GroupsMapping (SecurityAccess)
 * @see OpenId (Provider)
 */
interface SecurityAccessInterface
{
    /**
     * Call this method to perform the control access, the process continues normally if you are authorized,
     * otherwise an exception will be thrown
     *
     * @param Configuration $configuration The authentication configuration
     * @param array<string,mixed> $identityProviderData Data fetched from the identity provider
     *
     * @return void
     * @see AttributePathFetcherInterface
     */
    public function validate(Configuration $configuration, array $identityProviderData): void;
}
