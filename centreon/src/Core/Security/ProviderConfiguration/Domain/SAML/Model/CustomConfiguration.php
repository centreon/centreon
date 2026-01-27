<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

    private function __construct()
    {
    }

    /**
     * @param array<string,mixed> $values
     *
     * @throws ConfigurationException|MissingLogoutUrlException
     */
    public static function createFromValues(array $values): self
    {
        $customConfiguration = new self();
        if (isset($values['is_active']) && $values['is_active']) {
            $customConfiguration->validateMandatoryFields($values);
        }
        $customConfiguration->setEntityIDUrl($values['entity_id_url']);
        $customConfiguration->setRemoteLoginUrl($values['remote_login_url']);
        $customConfiguration->setPublicCertificate($values['certificate']);
        $customConfiguration->setLogoutFrom($values['logout_from']);
        if (isset($values['is_forced']) && $values['is_forced'] === true) {
            $customConfiguration->setLogoutFrom(self::LOGOUT_FROM_CENTREON_AND_IDP);
        }

        $customConfiguration->setLogoutFromUrl($values['logout_from_url']);
        $customConfiguration->setUserIdAttribute($values['user_id_attribute']);
        $customConfiguration->setRequestedAuthnContext($values['requested_authn_context'] ?? false);
        $customConfiguration->setRequestedAuthnContextComparison(
            RequestedAuthnContextComparisonEnum::tryFrom($values['requested_authn_context_comparison'])
            ?? throw ConfigurationException::invalidRequestedAuthnContextComparison($values['requested_authn_context_comparison'])
        );
        $customConfiguration->setAutoImportEnabled($values['auto_import']);
        $customConfiguration->setUserNameBindAttribute($values['fullname_bind_attribute']);
        $customConfiguration->setEmailBindAttribute($values['email_bind_attribute']);
        $customConfiguration->setContactTemplate($values['contact_template']);
        $customConfiguration->setAuthenticationConditions($values['authentication_conditions']);
        $customConfiguration->setACLConditions($values['roles_mapping']);
        $customConfiguration->setGroupsMapping($values['groups_mapping']);

        return $customConfiguration;
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

    public function hasRequestedAuthnContext(): bool
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

        $this->validateRequestedAuthnContextConfiguration($json);
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

    /**
     * @param array<string,mixed> $json
     *
     * @throws ConfigurationException
     */
    private function validateRequestedAuthnContextConfiguration(array $json): void
    {
        if (isset($json['requested_authn_context']) && $json['requested_authn_context'] === true) {
            if (! isset($json['requested_authn_context_comparison'])) {
                throw ConfigurationException::missingRequestedAuthnContextComparison();
            }
            if (RequestedAuthnContextComparisonEnum::tryFrom($json['requested_authn_context_comparison']) === null) {
                throw ConfigurationException::invalidRequestedAuthnContextComparison(
                    $json['requested_authn_context_comparison']
                );
            }
        }
    }
}
