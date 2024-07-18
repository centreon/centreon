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

namespace Core\Security\ProviderConfiguration\Application\OpenId\UseCase\PartialUpdateOpenIdConfiguration;

use Core\Common\Application\Type\NoValue;

/**
 * @phpstan-type _RoleMapping array{
 *  is_enabled: NoValue|bool,
 *  apply_only_first_role: NoValue|bool,
 *  attribute_path: NoValue|string,
 *  endpoint: NoValue|array{
 *      type: string,
 *      custom_endpoint:string|null
 *  },
 *  relations:NoValue|array<array{
 *      claim_value: string,
 *      access_group_id: int,
 *      priority: int
 *  }>
 * }
 * @phpstan-type _GroupsMapping array{
 *  is_enabled: NoValue|bool,
 *  attribute_path: NoValue|string,
 *  endpoint: NoValue|array{
 *      type: string,
 *      custom_endpoint:string|null
 *  },
 *  relations:NoValue|array<array{
 *      group_value: string,
 *      contact_group_id: int
 *  }>
 * }
 * @phpstan-type _AuthConditions array{
 *  is_enabled: NoValue|bool,
 *  attribute_path: NoValue|string,
 *  authorized_values: NoValue|string[],
 *  trusted_client_addresses: NoValue|string[],
 *  blacklist_client_addresses: NoValue|string[],
 *  endpoint: NoValue|array{
 *      type: string,
 *      custom_endpoint:string|null
 *  }
 * }
 * @phpstan-type _PartialUpdateOpenIdConfigurationRequest array{
 *      is_active:NoValue|bool,
 *      is_forced:NoValue|bool,
 *      base_url:NoValue|string|null,
 *      authorization_endpoint:NoValue|string|null,
 *      token_endpoint:NoValue|string|null,
 *      introspection_token_endpoint:NoValue|string|null,
 *      userinfo_endpoint:NoValue|string|null,
 *      endsession_endpoint:NoValue|string|null,
 *      connection_scopes:NoValue|string[],
 *      login_claim:NoValue|string|null,
 *      client_id:NoValue|string|null,
 *      client_secret:NoValue|string|null,
 *      authentication_type:NoValue|string|null,
 *      verify_peer:NoValue|bool,
 *      auto_import:NoValue|bool,
 *      contact_template:NoValue|array{id:int,name:string}|null,
 *      email_bind_attribute:NoValue|string|null,
 *      fullname_bind_attribute:NoValue|string|null,
 *      redirect_url:NoValue|string|null,
 *      authentication_conditions:array{
 *          is_enabled:NoValue|bool,
 *          attribute_path:NoValue|string,
 *          authorized_values:NoValue|string[],
 *          trusted_client_addresses:NoValue|string[],
 *          blacklist_client_addresses:NoValue|string[],
 *          endpoint:NoValue|array{type:string,custom_endpoint:string|null}
 *      },
 *      roles_mapping:array{
 *          is_enabled:NoValue|bool,
 *          apply_only_first_role:NoValue|bool,
 *          attribute_path:NoValue|string,
 *          endpoint:NoValue|array{type:string,custom_endpoint:string|null},
 *          relations:NoValue|array<array{claim_value:string,access_group_id:int,priority:int}>
 *      },
 *      groups_mapping:array{
 *          is_enabled:NoValue|bool,
 *          attribute_path:NoValue|string,
 *          endpoint:NoValue|array{type:string,custom_endpoint:string|null},
 *          relations:NoValue|array<array{group_value:string,contact_group_id:int}>
 *      }
 *  }
 */
final class PartialUpdateOpenIdConfigurationRequest
{
    /**
     * @param NoValue|bool $isActive
     * @param NoValue|bool $isForced
     * @param NoValue|string|null $baseUrl
     * @param NoValue|string|null $authorizationEndpoint
     * @param NoValue|string|null $tokenEndpoint
     * @param NoValue|string|null $introspectionTokenEndpoint
     * @param NoValue|string|null $userInformationEndpoint
     * @param NoValue|string|null $endSessionEndpoint
     * @param NoValue|string[] $connectionScopes
     * @param NoValue|string|null $loginClaim
     * @param NoValue|string|null $clientId
     * @param NoValue|string|null $clientSecret
     * @param NoValue|string|null $authenticationType
     * @param NoValue|bool $verifyPeer
     * @param NoValue|bool $isAutoImportEnabled
     * @param NoValue|array{id: int, name: string}|null $contactTemplate
     * @param NoValue|string|null $emailBindAttribute
     * @param NoValue|string|null $userNameBindAttribute
     * @param NoValue|string|null $redirectUrl
     * @param _AuthConditions $authenticationConditions
     * @param _RoleMapping $rolesMapping
     * @param _GroupsMapping $groupsMapping
     */
    public function __construct(
        public NoValue|bool $isActive = new NoValue(),
        public NoValue|bool $isForced = new NoValue(),
        public NoValue|string|null $baseUrl = new NoValue(),
        public NoValue|string|null $authorizationEndpoint = new NoValue(),
        public NoValue|string|null $tokenEndpoint = new NoValue(),
        public NoValue|string|null $introspectionTokenEndpoint = new NoValue(),
        public NoValue|string|null $userInformationEndpoint = new NoValue(),
        public NoValue|string|null $endSessionEndpoint = new NoValue(),
        public NoValue|array $connectionScopes = new NoValue(),
        public NoValue|string|null $loginClaim = new NoValue(),
        public NoValue|string|null $clientId = new NoValue(),
        public NoValue|string|null $clientSecret = new NoValue(),
        public NoValue|string|null $authenticationType = new NoValue(),
        public NoValue|bool $verifyPeer = new NoValue(),
        public NoValue|bool $isAutoImportEnabled = new NoValue(),
        public NoValue|array|null $contactTemplate = new NoValue(),
        public NoValue|string|null $emailBindAttribute = new NoValue(),
        public NoValue|string|null $userNameBindAttribute = new NoValue(),
        public NoValue|string|null $redirectUrl = new NoValue(),
        public array $authenticationConditions = [
            'is_enabled' => new NoValue(),
            'attribute_path' => new NoValue(),
            'authorized_values' => new NoValue(),
            'trusted_client_addresses' => new NoValue(),
            'blacklist_client_addresses' => new NoValue(),
            'endpoint' => new NoValue(),
        ],
        public array $rolesMapping = [
            'is_enabled' => new NoValue(),
            'apply_only_first_role' => new NoValue(),
            'attribute_path' => new NoValue(),
            'endpoint' => new NoValue(),
            'relations' => new NoValue(),
        ],
        public array $groupsMapping = [
            'is_enabled' => new NoValue(),
            'attribute_path' => new NoValue(),
            'endpoint' => new NoValue(),
            'relations' => new NoValue(),
        ],
    ) {
    }

    /**
     * @return _PartialUpdateOpenIdConfigurationRequest
     */
    public function toArray(): array
    {
        return [
            'is_forced' => $this->isForced,
            'is_active' => $this->isActive,
            'contact_template' => $this->contactTemplate,
            'auto_import' => $this->isAutoImportEnabled,
            'client_id' => $this->clientId,
            'authentication_type' => $this->authenticationType,
            'authorization_endpoint' => $this->authorizationEndpoint,
            'base_url' => $this->baseUrl,
            'client_secret' => $this->clientSecret,
            'connection_scopes' => $this->connectionScopes,
            'email_bind_attribute' => $this->emailBindAttribute,
            'endsession_endpoint' => $this->endSessionEndpoint,
            'introspection_token_endpoint' => $this->introspectionTokenEndpoint,
            'login_claim' => $this->loginClaim,
            'token_endpoint' => $this->tokenEndpoint,
            'userinfo_endpoint' => $this->userInformationEndpoint,
            'fullname_bind_attribute' => $this->userNameBindAttribute,
            'verify_peer' => $this->verifyPeer,
            'authentication_conditions' => $this->authenticationConditions,
            'groups_mapping' => $this->groupsMapping,
            'roles_mapping' => $this->rolesMapping,
            'redirect_url' => $this->redirectUrl,
        ];
    }
}
