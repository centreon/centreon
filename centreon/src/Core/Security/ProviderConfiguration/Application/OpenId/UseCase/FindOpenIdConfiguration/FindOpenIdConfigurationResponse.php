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

namespace Core\Security\ProviderConfiguration\Application\OpenId\UseCase\FindOpenIdConfiguration;

use Core\Contact\Domain\Model\ContactTemplate;
use Core\Security\ProviderConfiguration\Domain\Model\ACLConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthenticationConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthorizationRule;
use Core\Security\ProviderConfiguration\Domain\Model\ContactGroupRelation;
use Core\Security\ProviderConfiguration\Domain\Model\Endpoint;
use Core\Security\ProviderConfiguration\Domain\Model\GroupsMapping;

/**
 * @phpstan-import-type _EndpointArray from Endpoint
 *
 * @phpstan-type _authorizationRules array{
 *     claim_value: string,
 *     access_group: array{
 *         id: int,
 *         name: string
 *     },
 *     priority: int,
 * }
 * @phpstan-type _aclConditions array{
 *     is_enabled: bool,
 *     apply_only_first_role: bool,
 *     attribute_path: string,
 *     endpoint: null|_EndpointArray,
 *     relations: array<_authorizationRules>,
 * }
 * @phpstan-type _authenticationConditions array{
 *     is_enabled: bool,
 *     attribute_path: string,
 *     authorized_values: string[],
 *     trusted_client_addresses: string[],
 *     blacklist_client_addresses: string[],
 *     endpoint: _EndpointArray|null
 * }
 * @phpstan-type _groupsMapping array{
 *     is_enabled: bool,
 *     attribute_path: string,
 *     endpoint: _EndpointArray|null,
 *     relations: array<
 *         array{
 *             group_value: string,
 *             contact_group: array{
 *                 id: int,
 *                 name: string
 *             }
 *         }
 *     >
 * }
 */
final class FindOpenIdConfigurationResponse
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

    /** @var _aclConditions|array{} */
    public array $aclConditions = [];

    /** @var _authenticationConditions|array{} */
    public array $authenticationConditions = [];

    /** @var array{}|_groupsMapping */
    public array $groupsMapping = [];

    public ?string $redirectUrl = null;

    /**
     * @param ContactTemplate $contactTemplate
     *
     * @return array{id: int, name: string}
     */
    public static function contactTemplateToArray(ContactTemplate $contactTemplate): array
    {
        return [
            'id' => $contactTemplate->getId(),
            'name' => $contactTemplate->getName(),
        ];
    }

    /**
     * @param AuthorizationRule[] $authorizationRules
     *
     * @return array<_authorizationRules>
     */
    public static function authorizationRulesToArray(array $authorizationRules): array
    {
        return array_map(fn(AuthorizationRule $authorizationRule) => [
            'claim_value' => $authorizationRule->getClaimValue(),
            'access_group' => [
                'id' => $authorizationRule->getAccessGroup()->getId(),
                'name' => $authorizationRule->getAccessGroup()->getName(),
            ],
            'priority' => $authorizationRule->getPriority(),
        ], $authorizationRules);
    }

    /**
     * @param AuthenticationConditions $authenticationConditions
     *
     * @return _authenticationConditions
     */
    public static function authenticationConditionsToArray(AuthenticationConditions $authenticationConditions): array
    {
        return [
            'is_enabled' => $authenticationConditions->isEnabled(),
            'attribute_path' => $authenticationConditions->getAttributePath(),
            'endpoint' => $authenticationConditions->getEndpoint()?->toArray(),
            'authorized_values' => $authenticationConditions->getAuthorizedValues(),
            'trusted_client_addresses' => $authenticationConditions->getTrustedClientAddresses(),
            'blacklist_client_addresses' => $authenticationConditions->getBlacklistClientAddresses(),
        ];
    }

    /**
     * @param GroupsMapping $groupsMapping
     *
     * @return _groupsMapping
     */
    public static function groupsMappingToArray(GroupsMapping $groupsMapping): array
    {
        $relations = self::contactGroupRelationsToArray($groupsMapping->getContactGroupRelations());

        return [
            'is_enabled' => $groupsMapping->isEnabled(),
            'attribute_path' => $groupsMapping->getAttributePath(),
            'endpoint' => $groupsMapping->getEndpoint()?->toArray(),
            'relations' => $relations,
        ];
    }

    /**
     * @param ContactGroupRelation[] $contactGroupRelations
     *
     * @return array<array{
     *   "group_value": string,
     *   "contact_group": array{
     *      "id": int,
     *      "name": string
     *   }
     * }>
     */
    public static function contactGroupRelationsToArray(array $contactGroupRelations): array
    {
        return array_map(
            fn(ContactGroupRelation $contactGroupRelation) => [
                'group_value' => $contactGroupRelation->getClaimValue(),
                'contact_group' => [
                    'id' => $contactGroupRelation->getContactGroup()->getId(),
                    'name' => $contactGroupRelation->getContactGroup()->getName(),
                ],
            ],
            $contactGroupRelations
        );
    }

    /**
     * @param ACLConditions $aclConditions
     *
     * @return _aclConditions
     */
    public static function aclConditionsToArray(ACLConditions $aclConditions): array
    {
        $relations = self::authorizationRulesToArray($aclConditions->getRelations());

        return [
            'is_enabled' => $aclConditions->isEnabled(),
            'apply_only_first_role' => $aclConditions->onlyFirstRoleIsApplied(),
            'attribute_path' => $aclConditions->getAttributePath(),
            'endpoint' => $aclConditions->getEndpoint()?->toArray(),
            'relations' => $relations,
        ];
    }
}
