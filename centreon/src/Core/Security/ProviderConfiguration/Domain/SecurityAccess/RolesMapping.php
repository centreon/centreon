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

namespace Core\Security\ProviderConfiguration\Domain\SecurityAccess;

use Centreon\Domain\Log\LoggerTrait;
use Core\Security\Authentication\Domain\Exception\AclConditionsException;
use Core\Security\ProviderConfiguration\Domain\Exception\ConfigurationException;
use Core\Security\ProviderConfiguration\Domain\LoginLoggerInterface;
use Core\Security\ProviderConfiguration\Domain\Model\ACLConditions;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\CustomConfiguration as OpenIdCustomConfiguration;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration as SamlCustomConfiguration;

/**
 * Configured conditions must be satisfied to be authorized and map IDP's roles and Centreon's ACLs.
 *
 * @see RolesMapping::validate()
 */
class RolesMapping implements SecurityAccessInterface
{
    use LoggerTrait;

    /** @var string */
    private string $scope = 'undefined';

    /** @var string[] */
    private array $conditionMatches = [];

    /**
     * @param LoginLoggerInterface $loginLogger
     */
    public function __construct(
        private readonly LoginLoggerInterface $loginLogger,
    ) {
    }

    /**
     * @param Configuration $configuration
     * @param array<string,mixed> $identityProviderData
     *
     * @throws AclConditionsException
     */
    public function validate(Configuration $configuration, array $identityProviderData): void
    {
        $this->scope = $configuration->getType();
        $customConfiguration = $configuration->getCustomConfiguration();
        if (
            ! $customConfiguration instanceof OpenIdCustomConfiguration
            && ! $customConfiguration instanceof SamlCustomConfiguration
        ) {
            throw ConfigurationException::unexpectedCustomConfiguration($customConfiguration::class);
        }
        $aclConditions = $customConfiguration->getACLConditions();
        if (! $aclConditions->isEnabled()) {
            $this->loginLogger->info($this->scope, 'Roles mapping is disabled');
            $this->info('Roles mapping is disabled');

            return;
        }

        $this->loginLogger->info($this->scope, 'Roles mapping is enabled');
        $this->info('Roles mapping is enabled');

        $attributePath[] = $aclConditions->getAttributePath();
        if ($configuration->getType() === Provider::OPENID) {
            $attributePath = explode('.', $aclConditions->getAttributePath());
        }

        foreach ($attributePath as $attribute) {
            $providerConditions = [];
            if (array_key_exists($attribute, $identityProviderData)) {
                $providerConditions = $identityProviderData[$attribute];
                $identityProviderData = $identityProviderData[$attribute];
            } else {
                break;
            }
        }

        if (is_string($providerConditions)) {
            $providerConditions = explode(',', $providerConditions);
        }

        $this->validateAclAttributeOrFail($providerConditions, $aclConditions);
    }

    /**
     * @inheritDoc
     */
    public function getConditionMatches(): array
    {
        return $this->conditionMatches;
    }

    /**
     * Validate roles mapping conditions.
     *
     * @param array<mixed> $conditions
     * @param ACLConditions $aclConditions
     *
     * @throws AclConditionsException
     */
    private function validateAclAttributeOrFail(array $conditions, ACLConditions $aclConditions): void
    {
        if (! array_is_list($conditions)) {
            $errorMessage = 'Invalid roles mapping (ACL) conditions format, array of strings expected';
            $this->error($errorMessage, [
                'authentication_condition_from_provider' => $conditions,
            ]);

            $this->loginLogger->exception(
                $this->scope,
                $errorMessage,
                AclConditionsException::invalidAclConditions()
            );

            throw AclConditionsException::invalidAclConditions();
        }
        $configuredClaimValues = $aclConditions->getClaimValues();
        if ($aclConditions->onlyFirstRoleIsApplied() && $configuredClaimValues !== []) {
            foreach ($configuredClaimValues as $claimValue) {
                if (in_array($claimValue, $conditions, true)) {
                    $this->conditionMatches = [$claimValue];
                    break;
                }
            }
        } else {
            $this->conditionMatches = array_intersect($conditions, $configuredClaimValues);
        }

        if ($this->conditionMatches === []) {
            $this->error(
                'Configured attribute value not found in roles mapping configuration',
                [
                    'configured_authorized_values' => $configuredClaimValues,
                    'provider_conditions' => $conditions,
                ]
            );

            $this->loginLogger->exception(
                $this->scope,
                'Configured attribute value not found in roles mapping configuration',
                AclConditionsException::invalidAclConditions()
            );

            throw AclConditionsException::conditionsNotFound();
        }

        $this->info('Role mapping relation found', [
            'conditions_matches' => $this->conditionMatches,
            'provider' => $conditions,
            'configured' => $configuredClaimValues,
        ]);

        $this->loginLogger->info(
            $this->scope,
            'Role mapping relation found',
            [
                'conditions_matches' => implode(', ', $this->conditionMatches),
                'provider' => $conditions,
                'configured' => $configuredClaimValues,
            ]
        );
    }
}
