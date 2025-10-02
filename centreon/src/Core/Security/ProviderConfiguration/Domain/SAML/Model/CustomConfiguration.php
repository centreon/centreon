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

namespace Core\Security\ProviderConfiguration\Domain\SAML\Model;

use Core\Contact\Domain\Model\ContactGroup;
use Core\Contact\Domain\Model\ContactTemplate;
use Core\Security\ProviderConfiguration\Domain\CustomConfigurationInterface;
use Core\Security\ProviderConfiguration\Domain\Exception\ConfigurationException;
use Core\Security\ProviderConfiguration\Domain\Model\ACLConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthenticationConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthorizationRule;
use Core\Security\ProviderConfiguration\Domain\Model\GroupsMapping;
use Core\Security\ProviderConfiguration\Domain\SAML\Exception\MissingLogoutUrlException;

final class CustomConfiguration implements CustomConfigurationInterface, SAMLCustomConfigurationInterface
{
    public const LOGOUT_FROM_CENTREON = false;
    public const LOGOUT_FROM_CENTREON_AND_IDP = true;

    /** @var array<AuthorizationRule> */
    private array $authorizationRules = [];

    private bool $isAutoImportEnabled = false;

    private ?ContactTemplate $contactTemplate = null;

    private ?string $emailBindAttribute = null;

    private ?string $userNameBindAttribute = null;

    private ?ContactGroup $contactGroup = null;

    private ACLConditions $aclConditions;

    private AuthenticationConditions $authenticationConditions;

    private GroupsMapping $groupsMapping;

    private string $remoteLoginUrl = '';

    private string $entityIdUrl = '';

    private ?string $publicCertificate = '';

    private string $userIdAttribute = '';

    private bool $requestedAuthnContext = false;

    private RequestedAuthnContextComparisonEnum $requestedAuthnContextComparison;

    private bool $logoutFrom = true;

    private ?string $logoutFromUrl = null;

    /**
     * @param array<string,mixed> $json
     *
     * @throws ConfigurationException|MissingLogoutUrlException
     */
    public function __construct(array $json)
    {
        $this->create($json);
    }

    public function getRemoteLoginUrl(): string
    {
        return $this->remoteLoginUrl;
    }

    public function setRemoteLoginUrl(string $value): void
    {
        $this->remoteLoginUrl = $value;
    }

    public function getEntityIDUrl(): string
    {
        return $this->entityIdUrl;
    }

    public function setEntityIDUrl(string $value): void
    {
        $this->entityIdUrl = $value;
    }

    public function getPublicCertificate(): ?string
    {
        return $this->publicCertificate;
    }

    public function setPublicCertificate(?string $value): void
    {
        $this->publicCertificate = $value;
    }

    public function getUserIdAttribute(): string
    {
        return $this->userIdAttribute;
    }

    public function setUserIdAttribute(string $value): void
    {
        $this->userIdAttribute = $value;
    }

    public function isRequestedAuthnContext(): bool
    {
        return $this->requestedAuthnContext;
    }

    public function setRequestedAuthnContext(bool $requestedAuthnContext): void
    {
        $this->requestedAuthnContext = $requestedAuthnContext;
    }

    public function getRequestedAuthnContextComparison(): RequestedAuthnContextComparisonEnum
    {
        return $this->requestedAuthnContextComparison;
    }

    public function setRequestedAuthnContextComparison(RequestedAuthnContextComparisonEnum $value): void
    {
        $this->requestedAuthnContextComparison = $value;
    }

    public function getLogoutFrom(): bool
    {
        return $this->logoutFrom;
    }

    public function setLogoutFrom(bool $value): void
    {
        $this->logoutFrom = $value;
    }

    public function getLogoutFromUrl(): ?string
    {
        return $this->logoutFromUrl;
    }

    public function setLogoutFromUrl(?string $value): void
    {
        $this->logoutFromUrl = $value;
    }

    /**
     * @return AuthorizationRule[]
     */
    public function getAuthorizationRules(): array
    {
        return $this->authorizationRules;
    }

    public function isAutoImportEnabled(): bool
    {
        return $this->isAutoImportEnabled;
    }

    public function getContactTemplate(): ?ContactTemplate
    {
        return $this->contactTemplate;
    }

    public function getEmailBindAttribute(): ?string
    {
        return $this->emailBindAttribute;
    }

    public function setEmailBindAttribute(?string $value): void
    {
        $this->emailBindAttribute = $value;
    }

    public function getUserNameBindAttribute(): ?string
    {
        return $this->userNameBindAttribute;
    }

    public function setUserNameBindAttribute(?string $value): void
    {
        $this->userNameBindAttribute = $value;
    }

    public function getContactGroup(): ?ContactGroup
    {
        return $this->contactGroup;
    }

    public function getACLConditions(): ACLConditions
    {
        return $this->aclConditions;
    }

    public function setAutoImportEnabled(bool $isAutoImportEnabled): self
    {
        $this->isAutoImportEnabled = $isAutoImportEnabled;

        return $this;
    }

    public function setContactTemplate(?ContactTemplate $contactTemplate): self
    {
        $this->contactTemplate = $contactTemplate;

        return $this;
    }

    /**
     * @param AuthorizationRule[] $authorizationRules
     */
    public function setAuthorizationRules(array $authorizationRules): self
    {
        $this->authorizationRules = [];
        foreach ($authorizationRules as $authorizationRule) {
            $this->addAuthorizationRule($authorizationRule);
        }

        return $this;
    }

    public function addAuthorizationRule(AuthorizationRule $authorizationRule): self
    {
        $this->authorizationRules[] = $authorizationRule;

        return $this;
    }

    public function setContactGroup(?ContactGroup $contactGroup): self
    {
        $this->contactGroup = $contactGroup;

        return $this;
    }

    public function setAuthenticationConditions(AuthenticationConditions $authenticationConditions): self
    {
        $this->authenticationConditions = $authenticationConditions;

        return $this;
    }

    public function getAuthenticationConditions(): AuthenticationConditions
    {
        return $this->authenticationConditions;
    }

    public function setGroupsMapping(GroupsMapping $groupsMapping): self
    {
        $this->groupsMapping = $groupsMapping;

        return $this;
    }

    public function getGroupsMapping(): GroupsMapping
    {
        return $this->groupsMapping;
    }

    /**
     * @param array<string,mixed> $json
     *
     * @throws ConfigurationException|MissingLogoutUrlException
     */
    public function create(array $json): void
    {
        if (isset($json['is_active']) && $json['is_active']) {
            $this->validateMandatoryFields($json);
        }
        $this->setEntityIDUrl($json['entity_id_url']);
        $this->setRemoteLoginUrl($json['remote_login_url']);
        $this->setPublicCertificate($json['certificate']);
        $this->setLogoutFrom($json['logout_from']);
        if (isset($json['is_forced']) && $json['is_forced'] === true) {
            $this->setLogoutFrom(self::LOGOUT_FROM_CENTREON_AND_IDP);
        }

        $this->setLogoutFromUrl($json['logout_from_url']);
        $this->setUserIdAttribute($json['user_id_attribute']);
        $this->setRequestedAuthnContextComparison(
            RequestedAuthnContextComparisonEnum::tryFrom($json['requested_authn_context_comparison'])
            ?? throw ConfigurationException::invalidRequestedAuthnContextComparison($json['requested_authn_context_comparison'])
        );
        $this->setAutoImportEnabled($json['auto_import']);
        $this->setUserNameBindAttribute($json['fullname_bind_attribute']);
        $this->setEmailBindAttribute($json['email_bind_attribute']);
        $this->setContactTemplate($json['contact_template']);
        $this->setAuthenticationConditions($json['authentication_conditions']);
        $this->setACLConditions($json['roles_mapping']);
        $this->setGroupsMapping($json['groups_mapping']);
    }

    private function setACLConditions(ACLConditions $aclConditions): self
    {
        $this->aclConditions = $aclConditions;

        return $this;
    }

    /**
     * @param array<string,mixed> $json
     *
     * @throws ConfigurationException|MissingLogoutUrlException
     */
    private function validateMandatoryFields(array $json): void
    {
        $mandatoryFields = [
            'is_active',
            'is_forced',
            'remote_login_url',
            'certificate',
            'user_id_attribute',
            'requested_authn_context',
            'logout_from',
        ];

        $emptyParameters = [];
        foreach ($mandatoryFields as $key) {
            if (! array_key_exists($key, $json)) {
                $emptyParameters[] = $key;
            }
        }

        if ($emptyParameters !== []) {
            throw ConfigurationException::missingMandatoryParameters($emptyParameters);
        }

        if ($json['auto_import'] === true) {
            $this->validateParametersForAutoImport(
                $json['contact_template'],
                $json['email_bind_attribute'],
                $json['fullname_bind_attribute']
            );
        }

        if (
            ($json['logout_from'] === true || (isset($json['is_forced']) && $json['is_forced'] === true))
            && empty($json['logout_from_url'])
        ) {
            throw MissingLogoutUrlException::create();
        }
    }

    /**
     * @throws ConfigurationException
     */
    private function validateParametersForAutoImport(
        ?ContactTemplate $contactTemplate,
        ?string $emailBindAttribute,
        ?string $userNameBindAttribute,
    ): void {
        $missingMandatoryParameters = [];
        if ($contactTemplate === null) {
            $missingMandatoryParameters[] = 'contact_template';
        }
        if (empty($emailBindAttribute)) {
            $missingMandatoryParameters[] = 'email_bind_attribute';
        }
        if (empty($userNameBindAttribute)) {
            $missingMandatoryParameters[] = 'fullname_bind_attribute';
        }
        if ($missingMandatoryParameters !== []) {
            throw ConfigurationException::missingAutoImportMandatoryParameters(
                $missingMandatoryParameters
            );
        }
    }
}
