<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

use Core\Security\ProviderConfiguration\Domain\SAML\Model\RequestedAuthnContextComparisonEnum;

final class UpdateSAMLConfigurationRequest
{
    public RequestedAuthnContextComparisonEnum $requestedAuthnContextComparison;

    /**
     * @param array{id: int, name: string}|null $contactTemplate
     * @param array{
     *     is_enabled: bool,
     *     apply_only_first_role: bool,
     *     attribute_path: string,
     *     relations: array<array{claim_value: string, access_group_id: int, priority: int}>
     * } $rolesMapping
     * @param array{
     *     is_enabled: bool,
     *     attribute_path: string,
     *     authorized_values: string[],
     *     trusted_client_addresses: string[],
     *     blacklist_client_addresses: string[]
     * } $authenticationConditions
     * @param array{
     *     is_enabled: bool,
     *     attribute_path: string,
     *     relations: array<array{group_value: string, contact_group_id: int}>
     * } $groupsMapping
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public bool $isActive = false,
        public bool $isForced = false,
        public string $remoteLoginUrl = '',
        public string $entityIdUrl = '',
        public ?string $publicCertificate = null,
        public string $userIdAttribute = '',
        public bool $requestedAuthnContext = false,
        string $requestedAuthnContextComparison = RequestedAuthnContextComparisonEnum::EXACT->value,
        public bool $logoutFrom = true,
        public ?string $logoutFromUrl = null,
        public bool $isAutoImportEnabled = false,
        public ?array $contactTemplate = null,
        public ?string $emailBindAttribute = null,
        public ?string $userNameBindAttribute = null,
        public array $rolesMapping = [
            'is_enabled' => false,
            'apply_only_first_role' => false,
            'attribute_path' => '',
            'relations' => [],
        ],
        public array $authenticationConditions = [
            'is_enabled' => false,
            'attribute_path' => '',
            'authorized_values' => [],
            'trusted_client_addresses' => [],
            'blacklist_client_addresses' => [],
        ],
        public array $groupsMapping = [
            'is_enabled' => false,
            'attribute_path' => '',
            'relations' => [],
        ],
    ) {
        $this->validateRequest($requestedAuthnContextComparison);

        $this->requestedAuthnContextComparison = RequestedAuthnContextComparisonEnum::tryFrom(
            $requestedAuthnContextComparison
        ) ?? throw new \InvalidArgumentException('Invalid requested authentication context comparison value');
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function validateRequest(string $requestedAuthnContextComparison): void
    {
        if ($this->isActive) {
            if ($this->publicCertificate === null || $this->publicCertificate === '') {
                throw new \InvalidArgumentException('Public certificate must be provided');
            }

            if ($this->entityIdUrl === '') {
                throw new \InvalidArgumentException('Entity ID URL must be provided');
            }

            if ($this->remoteLoginUrl === '') {
                throw new \InvalidArgumentException('Remote login URL must be provided');
            }

            if ($this->userIdAttribute === '') {
                throw new \InvalidArgumentException('User ID attribute must be provided');
            }

            if (
                $this->requestedAuthnContext
                && RequestedAuthnContextComparisonEnum::tryFrom($requestedAuthnContextComparison) === null
            ) {
                throw new \InvalidArgumentException(
                    'Invalid requested authentication context comparison value, must be one of: ' . implode(
                        ', ',
                        array_map(fn ($e) => $e->value, RequestedAuthnContextComparisonEnum::cases())
                    )
                );
            }

            if ($this->logoutFrom && ($this->logoutFromUrl === null || $this->logoutFromUrl === '')) {
                throw new \InvalidArgumentException('Logout from URL must be provided when logout from is enabled');
            }

            if ($this->isAutoImportEnabled) {
                if ($this->emailBindAttribute === null || $this->emailBindAttribute === '') {
                    throw new \InvalidArgumentException(
                        'Email bind attribute must be provided when auto import is enabled'
                    );
                }
                if ($this->userNameBindAttribute === null || $this->userNameBindAttribute === '') {
                    throw new \InvalidArgumentException(
                        'Fullname bind attribute must be provided when auto import is enabled'
                    );
                }
                if (empty($this->contactTemplate)) {
                    throw new \InvalidArgumentException(
                        'Contact template must be provided when auto import is enabled'
                    );
                }
            }
        }
    }

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
            'requested_authn_context_comparison' => $this->requestedAuthnContextComparison->value,
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
