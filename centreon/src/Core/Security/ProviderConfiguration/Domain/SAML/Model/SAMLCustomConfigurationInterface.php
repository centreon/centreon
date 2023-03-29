<?php

/*
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
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
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Core\Security\ProviderConfiguration\Domain\SAML\Model;

use Core\Contact\Domain\Model\ContactGroup;
use Core\Contact\Domain\Model\ContactTemplate;
use Core\Security\ProviderConfiguration\Domain\Model\ACLConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthenticationConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthorizationRule;
use Core\Security\ProviderConfiguration\Domain\Model\GroupsMapping;

interface SAMLCustomConfigurationInterface
{

    /**
     * @return AuthorizationRule[]
     */
    public function getAuthorizationRules(): array;

    /**
     * @return bool
     */
    public function isAutoImportEnabled(): bool;

    /**
     * @return ContactTemplate|null
     */
    public function getContactTemplate(): ?ContactTemplate;

    /**
     * @return string|null
     */
    public function getEmailBindAttribute(): ?string;

    /**
     * @return string|null
     */
    public function getUserNameBindAttribute(): ?string;

    /**
     * @return ContactGroup|null
     */
    public function getContactGroup(): ?ContactGroup;

    /**
     * @return AuthenticationConditions
     */
    public function getAuthenticationConditions(): AuthenticationConditions;
}
