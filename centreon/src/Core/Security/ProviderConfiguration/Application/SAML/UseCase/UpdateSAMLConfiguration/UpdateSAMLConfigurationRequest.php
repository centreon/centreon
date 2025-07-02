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

namespace Core\Security\ProviderConfiguration\Application\SAML\UseCase\UpdateSAMLConfiguration;

/**
 * @phpstan-type _RolesMapping array{
 *     is_enabled: bool,
 *     apply_only_first_role: bool,
 *     attribute_path: string,
 *     relations: array<array{claim_value: string, access_group_id: int, priority: int}>,
 * }
 */
final class UpdateSAMLConfigurationRequest
{
    /** @var bool */
    public bool $isActive = false;

    /** @var bool */
    public bool $isForced = false;

    /** @var string */
    public string $remoteLoginUrl = '';

    /** @var string */
    public string $entityIdUrl = '';

    /** @var string */
    public ?string $publicCertificate = '';

    /** @var string */
    public string $userIdAttribute = '';

    /** @var string */
    public string $requestedAuthnContext = 'minimum';

    /** @var bool */
    public bool $logoutFrom = true;

    /** @var string|null */
    public ?string $logoutFromUrl = null;

    /** @var bool */
    public bool $isAutoImportEnabled = false;

    /** @var array{id: int, name: string}|null */
    public ?array $contactTemplate = null;

    /** @var string|null */
    public ?string $emailBindAttribute = null;

    /** @var string|null */
    public ?string $userNameBindAttribute = null;

    /** @var _RolesMapping */
    public array $rolesMapping = [
        'is_enabled' => false,
        'apply_only_first_role' => false,
        'attribute_path' => '',
        'relations' => [],
    ];

    /**
     * @var array{
     *  "is_enabled": bool,
     *  "attribute_path": string,
     *  "authorized_values": string[],
     *  "trusted_client_addresses": string[],
     *  "blacklist_client_addresses": string[]
     * }
     */
    public array $authenticationConditions = [
        'is_enabled' => false,
        'attribute_path' => '',
        'authorized_values' => [],
        'trusted_client_addresses' => [],
        'blacklist_client_addresses' => [],
    ];

    /**
     * @var array{
     *  "is_enabled": bool,
     *  "attribute_path": string,
     *  "relations":array<array{
     *      "group_value": string,
     *      "contact_group_id": int
     *  }>
     * }
     */
    public array $groupsMapping = [
        'is_enabled' => false,
        'attribute_path' => '',
        'relations' => [],
    ];

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'entity_id_url' => $this->entityIdUrl,
            'remote_login_url' => $this->remoteLoginUrl,
            'user_id_attribute' => $this->userIdAttribute,
            'requested_authn_context' => $this->requestedAuthnContext,
            'certificate' => $this->publicCertificate,
            'logout_from' => $this->logoutFrom,
            'logout_from_url' => $this->logoutFromUrl,
            'contact_template' => $this->contactTemplate,
            'auto_import' => $this->isAutoImportEnabled,
            'email_bind_attribute' => $this->emailBindAttribute,
            'fullname_bind_attribute' => $this->userNameBindAttribute,
            'authentication_conditions' => $this->authenticationConditions,
            'groups_mapping' => $this->groupsMapping,
            'roles_mapping' => $this->rolesMapping,
        ];
    }
}
