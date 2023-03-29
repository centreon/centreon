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

use Centreon\Domain\Entity\ContactGroup;
use Centreon\Domain\Log\LoggerTrait;
use Core\Security\Authentication\Domain\Exception\AuthenticationConditionsException;
use Core\Security\ProviderConfiguration\Domain\LoginLoggerInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\ContactGroupRelation;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\SecurityAccess\AttributePath\AttributePathFetcher;

/**
 * Configured conditions must be satisfied to be authorized and map IDP's groups and Centreon's groups
 *
 * @see GroupsMapping::validate()
 */
class GroupsMapping implements SecurityAccessInterface
{
    use LoggerTrait;

    /**
     * @var string
     */
    private string $scope = 'undefined';

    /**
     * @var ContactGroup[]
     */
    private array $userContactGroups = [];

    /**
     * @param LoginLoggerInterface $loginLogger
     * @param AttributePathFetcher $attributePathFetcher
     */
    public function __construct(
        private readonly LoginLoggerInterface $loginLogger,
        private readonly AttributePathFetcher $attributePathFetcher
    ) {
    }

    /**
     * @param Configuration $configuration
     * @param array<string,mixed> $identityProviderData
     * @return void
     * @throws AuthenticationConditionsException
     */
    public function validate(Configuration $configuration, array $identityProviderData): void
    {
        $this->scope = $configuration->getType();
        $customConfiguration = $configuration->getCustomConfiguration();
        $groupsMapping = $customConfiguration->getGroupsMapping();

        if (!$groupsMapping->isEnabled()) {
            $this->loginLogger->info($this->scope, "Groups Mapping disabled");
            $this->info("Groups Mapping disabled");
            return;
        }

        $this->loginLogger->info($this->scope, "Groups Mapping Enabled");
        $this->info("Groups Mapping Enabled");

        $groupsAttributePath[] = $groupsMapping->getAttributePath();
        if ($configuration->getType() === Provider::OPENID) {
            $groupsAttributePath = explode(".", $groupsMapping->getAttributePath());
        }

        $this->loginLogger->info($this->scope, "Configured groups mapping attribute path found", $groupsAttributePath);
        $this->info("Configured groups mapping attribute path found", $groupsAttributePath);

        $groupRelationContextDebug = array_map(
            function (ContactGroupRelation $contactGroupRelation) {
                return [
                    "group claim" => $contactGroupRelation->getClaimValue(),
                    "contact group" => $contactGroupRelation->getContactGroup()->getName()
                ];
            },
            $groupsMapping->getContactGroupRelations()
        );

        $this->loginLogger->info($this->scope, "Groups relations", $groupRelationContextDebug);
        $this->info("Groups relations", $groupRelationContextDebug);

        foreach ($groupsAttributePath as $attribute) {
            $providerGroups = [];
            if (array_key_exists($attribute, $identityProviderData)) {
                $providerGroups = $identityProviderData[$attribute];
                $identityProviderData = $identityProviderData[$attribute];
            } else {
                break;
            }
        }
        if (is_string($providerGroups)) {
            $providerGroups = explode(",", $providerGroups);
        }

        $this->validateGroupsMappingAttributeOrFail($providerGroups, $groupsMapping->getContactGroupRelations());
    }

    /**
     * @param array $providerGroupsMapping
     * @param array $contactGroupRelations
     * @return void
     * @throws AuthenticationConditionsException
     */
    private function validateGroupsMappingAttributeOrFail(
        array $providerGroupsMapping,
        array $contactGroupRelations
    ): void {
        if (array_is_list($providerGroupsMapping) === false) {
            $errorMessage = "Invalid authentication conditions format, array of strings expected";
            $this->error(
                $errorMessage,
                [
                    "authentication_condition_from_provider" => $providerGroupsMapping
                ]
            );
            $this->loginLogger->exception(
                $this->scope,
                $errorMessage,
                AuthenticationConditionsException::invalidAuthenticationConditions()
            );

            throw AuthenticationConditionsException::invalidAuthenticationConditions();
        }
        $claimsFromProvider = [];
        foreach ($contactGroupRelations as $contactGroupRelation) {
            $claimsFromProvider[] = $contactGroupRelation->getClaimValue();
        }
        $groupsMatches = array_intersect($providerGroupsMapping, $claimsFromProvider);
        $this->userContactGroups = [];
        foreach ($groupsMatches as $groupsMatch) {
            foreach ($contactGroupRelations as $contactGroupRelation) {
                if ($contactGroupRelation->getClaimValue() === $groupsMatch) {
                    $this->userContactGroups[] = $contactGroupRelation->getContactGroup();
                }
            }
        }
        if (empty($groupsMatches)) {
            $this->error(
                "Configured attribute value not found in groups mapping endpoint",
                [
                    "provider_groups_mapping" => $providerGroupsMapping,
                    "configured_groups_mapping" => $claimsFromProvider
                ]
            );

            $this->loginLogger->exception(
                $this->scope,
                "Configured attribute value not found in groups mapping endpoint: %s, message: %s",
                AuthenticationConditionsException::conditionsNotFound()
            );
        }
        $this->info("Groups found", ["group" => $groupsMatches]);
        $this->loginLogger->info($this->scope, "Groups found", $groupsMatches);
    }

    /**
     * @return ContactGroup[]
     */
    public function getUserContactGroups(): array
    {
        return $this->userContactGroups;
    }
}
