<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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
use Core\Security\ProviderConfiguration\Domain\CustomConfigurationInterface;
use Core\Security\ProviderConfiguration\Domain\Exception\ConfigurationException;
use Core\Security\ProviderConfiguration\Domain\Model\ACLConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthenticationConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthorizationRule;
use Core\Security\ProviderConfiguration\Domain\Model\GroupsMapping;
use Core\Security\ProviderConfiguration\Domain\SAML\Exception\MissingLogoutUrlException;
use TypeError;

final class CustomConfiguration implements CustomConfigurationInterface, SAMLCustomConfigurationInterface
{
    const LOGOUT_FROM_CENTREON = false;
    const LOGOUT_FROM_CENTREON_AND_IDP = true;

    /**
     * @var array<AuthorizationRule>
     */
    private array $authorizationRules = [];

    /**
     * @var boolean
     */
    private bool $isAutoImportEnabled = false;

    /**
     * @var ContactTemplate|null
     */
    private ?ContactTemplate $contactTemplate = null;

    /**
     * @var string|null
     */
    private ?string $emailBindAttribute = null;

    /**
     * @var string|null
     */
    private ?string $userNameBindAttribute = null;

    /**
     * @var ContactGroup|null
     */
    private ?ContactGroup $contactGroup = null;

    /**
     * @var ACLConditions
     */
    private ACLConditions $aclConditions;

    /**
     * @var AuthenticationConditions
     */
    private AuthenticationConditions $authenticationConditions;

    /**
     * @var GroupsMapping
     */
    private GroupsMapping $groupsMapping;

    /**
     * @var string
     */
    private string $remoteLoginUrl = '';

    /**
     * @var string
     */
    private string $entityIdUrl = '';

    /**
     * @var string|null
     */
    private ?string $publicCertificate = '';

    /**
     * @var string
     */
    private string $userIdAttribute = '';

    /**
     * @var bool
     */
    private bool $logoutFrom = true;

    /**
     * @var string|null
     */
    private ?string $logoutFromUrl = null;

    /**
     * @param array<string,mixed> $json
     * @throws ConfigurationException
     */
    public function __construct(array $json)
    {
        $this->create($json);
    }

    /**
     * @return string
     */
    public function getRemoteLoginUrl(): string
    {
        return $this->remoteLoginUrl;
    }

    /**
     * @param string $value
     * @return void
     */
    public function setRemoteLoginUrl(string $value): void
    {
        $this->remoteLoginUrl = $value;
    }

    /**
     * @return string
     */
    public function getEntityIDUrl(): string
    {
        return $this->entityIdUrl;
    }

    /**
     * @param string $value
     * @return void
     */
    public function setEntityIDUrl(string $value): void
    {
        $this->entityIdUrl = $value;
    }

    /**
     * @return string|null
     */
    public function getPublicCertificate(): ?string
    {
        return $this->publicCertificate;
    }

    /**
     * @param string|null $value
     * @return void
     */
    public function setPublicCertificate(?string $value): void
    {
        $this->publicCertificate = $value;
    }

    /**
     * @return string
     */
    public function getUserIdAttribute(): string
    {
        return $this->userIdAttribute;
    }

    /**
     * @param string $value
     * @return void
     */
    public function setUserIdAttribute(string $value): void
    {
        $this->userIdAttribute = $value;
    }

    /**
     * @return bool
     */
    public function getLogoutFrom(): bool
    {
        return $this->logoutFrom;
    }

    /**
     * @param bool $value
     * @return void
     */
    public function setLogoutFrom(bool $value): void
    {
        $this->logoutFrom = $value;
    }


    /**
     * @return string|null
     */
    public function getLogoutFromUrl(): ?string
    {
        return $this->logoutFromUrl;
    }

    /**
     * @param string|null $value
     * @return void
     */
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

    /**
     * @return bool
     */
    public function isAutoImportEnabled(): bool
    {
        return $this->isAutoImportEnabled;
    }

    /**
     * @return ContactTemplate|null
     */
    public function getContactTemplate(): ?ContactTemplate
    {
        return $this->contactTemplate;
    }

    /**
     * @return string|null
     */
    public function getEmailBindAttribute(): ?string
    {
        return $this->emailBindAttribute;
    }

    /**
     * @param string $value
     * @return void
     */
    public function setEmailBindAttribute(?string $value): void
    {
        $this->emailBindAttribute = $value;
    }

    /**
     * @return string|null
     */
    public function getUserNameBindAttribute(): ?string
    {
        return $this->userNameBindAttribute;
    }

    /**
     * @param string $value
     * @return void
     */
    public function setUserNameBindAttribute(?string $value): void
    {
        $this->userNameBindAttribute = $value;
    }

    /**
     * @return ContactGroup|null
     */
    public function getContactGroup(): ?ContactGroup
    {
        return $this->contactGroup;
    }

    /**
     * @return ACLConditions
     */
    public function getACLConditions(): ACLConditions
    {
        return $this->aclConditions;
    }

    /**
     * @param boolean $isAutoImportEnabled
     * @return self
     */
    public function setAutoImportEnabled(bool $isAutoImportEnabled): self
    {
        $this->isAutoImportEnabled = $isAutoImportEnabled;

        return $this;
    }

    /**
     * @param ContactTemplate|null $contactTemplate
     * @return self
     */
    public function setContactTemplate(?ContactTemplate $contactTemplate): self
    {
        $this->contactTemplate = $contactTemplate;

        return $this;
    }

    /**
     * @param AuthorizationRule[] $authorizationRules
     * @return self
     * @throws TypeError
     */
    public function setAuthorizationRules(array $authorizationRules): self
    {
        $this->authorizationRules = [];
        foreach ($authorizationRules as $authorizationRule) {
            $this->addAuthorizationRule($authorizationRule);
        }

        return $this;
    }

    /**
     * @param AuthorizationRule $authorizationRule
     * @return self
     */
    public function addAuthorizationRule(AuthorizationRule $authorizationRule): self
    {
        $this->authorizationRules[] = $authorizationRule;

        return $this;
    }

    /**
     * @param ContactGroup|null $contactGroup
     * @return self
     */
    public function setContactGroup(?ContactGroup $contactGroup): self
    {
        $this->contactGroup = $contactGroup;

        return $this;
    }

    /**
     * @param AuthenticationConditions $authenticationConditions
     * @return self
     */
    public function setAuthenticationConditions(AuthenticationConditions $authenticationConditions): self
    {
        $this->authenticationConditions = $authenticationConditions;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getAuthenticationConditions(): AuthenticationConditions
    {
        return $this->authenticationConditions;
    }

    /**
     * @param GroupsMapping $groupsMapping
     * @return self
     */
    public function setGroupsMapping(GroupsMapping $groupsMapping): self
    {
        $this->groupsMapping = $groupsMapping;
        return $this;
    }

    /**
     * @return GroupsMapping
     */
    public function getGroupsMapping(): GroupsMapping
    {
        return $this->groupsMapping;
    }

    /**
     * @param array<string,mixed> $json
     * @throws ConfigurationException
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
        $this->setAutoImportEnabled($json['auto_import']);
        $this->setUserNameBindAttribute($json['fullname_bind_attribute']);
        $this->setEmailBindAttribute($json['email_bind_attribute']);
        $this->setContactTemplate($json['contact_template']);
        $this->setAuthenticationConditions($json['authentication_conditions']);
        $this->setACLConditions($json['roles_mapping']);
        $this->setGroupsMapping($json['groups_mapping']);
    }

    /**
     * @param ACLConditions $aclConditions
     * @return CustomConfiguration
     */
    private function setACLConditions(ACLConditions $aclConditions): self
    {
        $this->aclConditions = $aclConditions;

        return $this;
    }

    /**
     * @param array<string,mixed> $json
     * @return void
     * @throws ConfigurationException
     */
    private function validateMandatoryFields(array $json): void
    {
        $mandatoryFields = [
            'is_active',
            'is_forced',
            'remote_login_url',
            'certificate',
            'user_id_attribute',
            'logout_from',
        ];

        foreach ($mandatoryFields as $key) {
            if (!array_key_exists($key, $json)) {
                $emptyParameters[] = $key;
            }
        }

        if (!empty($emptyParameters)) {
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
            ($json['logout_from'] === true || (isset($json['is_forced']) && $json['is_forced'] === true)) &&
            empty($json['logout_from_url'])
        ) {
            throw MissingLogoutUrlException::create();
        }
    }

    /**
     * Validate mandatory parameters for auto import
     *
     * @param ContactTemplate|null $contactTemplate
     * @param string|null $emailBindAttribute
     * @param string|null $userNameBindAttribute
     * @throws ConfigurationException
     */
    private function validateParametersForAutoImport(
        ?ContactTemplate $contactTemplate,
        ?string $emailBindAttribute,
        ?string $userNameBindAttribute
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
        if (!empty($missingMandatoryParameters)) {
            throw ConfigurationException::missingAutoImportMandatoryParameters(
                $missingMandatoryParameters
            );
        }
    }
}
