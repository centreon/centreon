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

namespace Core\Security\ProviderConfiguration\Application\OpenId\UseCase\UpdateOpenIdConfiguration;

/**
 * @phpstan-type _RoleMapping array{
 *  is_enabled: bool,
 *  apply_only_first_role: bool,
 *  attribute_path: string,
 *  endpoint: array{
 *      type: string,
 *      custom_endpoint:string|null
 *  },
 *  relations:array<array{
 *      claim_value: string,
 *      access_group_id: int,
 *      priority: int
 *  }>
 * }
 * @phpstan-type _GroupMapping array{
 *   is_enabled: bool,
 *   attribute_path: string,
 *   endpoint: array{
 *       type: string,
 *       custom_endpoint:string|null
 *   },
 *   relations:array<array{
 *       group_value: string,
 *       contact_group_id: int
 *   }>
 *  }
 * @phpstan-type _AuthenticationConditions array{
 *   is_enabled: bool,
 *   attribute_path: string,
 *   authorized_values: string[],
 *   trusted_client_addresses: string[],
 *   blacklist_client_addresses: string[],
 *   endpoint: array{
 *       type: string,
 *       custom_endpoint:string|null
 *   }
 *  }
 * @phpstan-type _UpdateOpenIdConfigurationRequest array{
 *     is_active: bool,
 *     is_forced: bool,
 *     base_url: string|null,
 *     authorization_endpoint: string|null,
 *     token_endpoint: string|null,
 *     introspection_token_endpoint: string|null,
 *     userinfo_endpoint: string|null,
 *     endsession_endpoint: string|null,
 *     connection_scopes: string[],
 *     login_claim: string|null,
 *     client_id: string|null,
 *     client_secret: string|null,
 *     authentication_type: string|null,
 *     verify_peer: bool,
 *     auto_import: bool,
 *     contact_template: array{id: int, name: string}|null,
 *     email_bind_attribute: string|null,
 *     fullname_bind_attribute: string|null,
 *     roles_mapping: _RoleMapping,
 *     authentication_conditions: _AuthenticationConditions,
 *     groups_mapping: _GroupMapping,
 *     redirect_url: string|null
 * }
 */
final class UpdateOpenIdConfigurationRequest
{
    /** @var bool */
    public bool $isActive = false;

    /** @var bool */
    public bool $isForced = false;

    /** @var string|null */
    public ?string $baseUrl = null;

    /** @var string|null */
    public ?string $authorizationEndpoint = null;

    /** @var string|null */
    public ?string $tokenEndpoint = null;

    /** @var string|null */
    public ?string $introspectionTokenEndpoint = null;

    /** @var string|null */
    public ?string $userInformationEndpoint = null;

    /** @var string|null */
    public ?string $endSessionEndpoint = null;

    /** @var string[] */
    public array $connectionScopes = [];

    /** @var string|null */
    public ?string $loginClaim = null;

    /** @var string|null */
    public ?string $clientId = null;

    /** @var string|null */
    public ?string $clientSecret = null;

    /** @var string|null */
    public ?string $authenticationType = null;

    /** @var bool */
    public bool $verifyPeer = false;

    /** @var bool */
    public bool $isAutoImportEnabled = false;

    /** @var array{id: int, name: string}|null */
    public ?array $contactTemplate = null;

    /** @var string|null */
    public ?string $emailBindAttribute = null;

    /** @var string|null */
    public ?string $userNameBindAttribute = null;

    /** @var _RoleMapping */
    public array $rolesMapping = [
        'is_enabled' => false,
        'apply_only_first_role' => false,
        'attribute_path' => '',
        'endpoint' => [
            'type' => 'introspection_endpoint',
            'custom_endpoint' => '',
        ],
        'relations' => [],
    ];

    /** @var _AuthenticationConditions */
    public array $authenticationConditions = [
        'is_enabled' => false,
        'attribute_path' => '',
        'authorized_values' => [],
        'trusted_client_addresses' => [],
        'blacklist_client_addresses' => [],
        'endpoint' => [
            'type' => 'introspection_endpoint',
            'custom_endpoint' => null,
        ],
    ];

    /** @var _GroupMapping */
    public array $groupsMapping = [
        'is_enabled' => false,
        'attribute_path' => '',
        'endpoint' => [
            'type' => 'introspection_endpoint',
            'custom_endpoint' => null,
        ],
        'relations' => [],
    ];

    public ?string $redirectUrl = null;

    /**
     * @return _UpdateOpenIdConfigurationRequest
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