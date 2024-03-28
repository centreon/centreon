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

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactTemplateRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Contact\Domain\Model\ContactTemplate;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\ProviderConfiguration\Application\OpenId\Repository\WriteOpenIdConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Domain\Exception\ConfigurationException;
use Core\Security\ProviderConfiguration\Domain\Model\ACLConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthenticationConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthorizationRule;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\ContactGroupRelation;
use Core\Security\ProviderConfiguration\Domain\Model\Endpoint;
use Core\Security\ProviderConfiguration\Domain\Model\GroupsMapping;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\CustomConfiguration;

/**
 * @phpstan-import-type _RoleMapping from UpdateOpenIdConfigurationRequest
 */
class UpdateOpenIdConfiguration
{
    use LoggerTrait;

    /**
     * @param WriteOpenIdConfigurationRepositoryInterface $repository
     * @param ReadContactTemplateRepositoryInterface $contactTemplateRepository
     * @param ReadContactGroupRepositoryInterface $contactGroupRepository
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param ProviderAuthenticationFactoryInterface $providerAuthenticationFactory
     */
    public function __construct(
        private WriteOpenIdConfigurationRepositoryInterface $repository,
        private ReadContactTemplateRepositoryInterface $contactTemplateRepository,
        private ReadContactGroupRepositoryInterface $contactGroupRepository,
        private ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private ProviderAuthenticationFactoryInterface $providerAuthenticationFactory
    ) {
    }

    /**
     * @param UpdateOpenIdConfigurationPresenterInterface $presenter
     * @param UpdateOpenIdConfigurationRequest $request
     */
    public function __invoke(
        UpdateOpenIdConfigurationPresenterInterface $presenter,
        UpdateOpenIdConfigurationRequest $request
    ): void {

        $this->info('Updating OpenID Provider');
        try {
            $provider = $this->providerAuthenticationFactory->create(Provider::OPENID);
            /**
             * @var Configuration $configuration
             */
            $configuration = $provider->getConfiguration();
            $configuration->update($request->isActive, $request->isForced);
            $requestArray = $request->toArray();

            $requestArray['contact_template'] = $request->contactTemplate
            && array_key_exists('id', $request->contactTemplate) !== null
                ? $this->getContactTemplateOrFail($request->contactTemplate)
                : null;
            $requestArray['roles_mapping'] = $this->createAclConditions($request->rolesMapping);
            $requestArray['authentication_conditions'] = $this->createAuthenticationConditions(
                $request->authenticationConditions
            );
            $requestArray['groups_mapping'] = $this->createGroupsMapping($request->groupsMapping);
            $requestArray['is_active'] = $request->isActive;

            $configuration->setCustomConfiguration(new CustomConfiguration($requestArray));

            $this->info('Updating OpenID Provider');
            $this->repository->updateConfiguration($configuration);
        } catch (AssertionException|AssertionFailedException|ConfigurationException $ex) {
            $this->error(
                'Unable to create OpenID Provider because one or several parameters are invalid',
                ['trace' => $ex->getTraceAsString()]
            );
            $presenter->setResponseStatus(new ErrorResponse($ex->getMessage()));

            return;
        } catch (\Throwable $ex) {
            $this->error('Error during Opend ID Provider Update', ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new UpdateOpenIdConfigurationErrorResponse());

            return;
        }

        $presenter->setResponseStatus(new NoContentResponse());
    }

    /**
     * Get Contact template or throw an Exception.
     *
     * @param array{id: int, name: string}|null $contactTemplateFromRequest
     *
     * @throws \Throwable|ConfigurationException
     *
     * @return ContactTemplate|null
     */
    private function getContactTemplateOrFail(?array $contactTemplateFromRequest): ?ContactTemplate
    {
        if ($contactTemplateFromRequest === null) {
            return null;
        }
        if (($contactTemplate = $this->contactTemplateRepository->find($contactTemplateFromRequest['id'])) === null) {
            throw ConfigurationException::contactTemplateNotFound(
                $contactTemplateFromRequest['name']
            );
        }

        return $contactTemplate;
    }

    /**
     * Create Authorization Rules.
     *
     * @param array<array{claim_value: string, access_group_id: int, priority: int}> $authorizationRulesFromRequest
     *
     * @throws \Throwable
     *
     * @return AuthorizationRule[]
     */
    private function createAuthorizationRules(array $authorizationRulesFromRequest): array
    {
        $this->info('Creating Authorization Rules');
        $accessGroupIds = $this->getAccessGroupIds($authorizationRulesFromRequest);

        if (empty($accessGroupIds)) {
            return [];
        }

        $foundAccessGroups = $this->accessGroupRepository->findByIds($accessGroupIds);

        $this->logNonExistentAccessGroupsIds($accessGroupIds, $foundAccessGroups);

        $authorizationRules = [];
        foreach ($authorizationRulesFromRequest as $authorizationRule) {
            $accessGroup = $this->findAccessGroupFromFoundAccessGroups(
                $authorizationRule['access_group_id'],
                $foundAccessGroups
            );
            if ($accessGroup !== null) {
                $authorizationRules[] = new AuthorizationRule(
                    $authorizationRule['claim_value'],
                    $accessGroup,
                    $authorizationRule['priority']
                );
            }
        }

        return $authorizationRules;
    }

    /**
     * @param _RoleMapping $rolesMapping
     *
     * @throws \Throwable
     *
     * @return ACLConditions
     */
    private function createAclConditions(array $rolesMapping): ACLConditions
    {
        $rules = $this->createAuthorizationRules($rolesMapping['relations']);

        return new ACLConditions(
            $rolesMapping['is_enabled'],
            $rolesMapping['apply_only_first_role'],
            $rolesMapping['attribute_path'],
            new Endpoint($rolesMapping['endpoint']['type'], $rolesMapping['endpoint']['custom_endpoint']),
            $rules
        );
    }

    /**
     * Add log for all the non existent access groups.
     *
     * @param int[] $accessGroupIdsFromRequest
     * @param AccessGroup[] $foundAccessGroups
     */
    private function logNonExistentAccessGroupsIds(array $accessGroupIdsFromRequest, array $foundAccessGroups): void
    {
        $foundAccessGroupsId = [];
        foreach ($foundAccessGroups as $foundAccessGroup) {
            $foundAccessGroupsId[] = $foundAccessGroup->getId();
        }

        $nonExistentAccessGroupsIds = array_diff($accessGroupIdsFromRequest, $foundAccessGroupsId);

        if (! empty($nonExistentAccessGroupsIds)) {
            $this->error('Access Groups not found', [
                'access_group_ids' => implode(', ', $nonExistentAccessGroupsIds),
            ]);
        }
    }

    /**
     * Compare the access group id sent in request with Access groups from database
     * Return the access group that have the same id than the access group id from the request.
     *
     * @param int $accessGroupIdFromRequest Access group id sent in the request
     * @param AccessGroup[] $foundAccessGroups Access groups found in data storage
     *
     * @return AccessGroup|null
     */
    private function findAccessGroupFromFoundAccessGroups(
        int $accessGroupIdFromRequest,
        array $foundAccessGroups
    ): ?AccessGroup {
        foreach ($foundAccessGroups as $foundAccessGroup) {
            if ($accessGroupIdFromRequest === $foundAccessGroup->getId()) {
                return $foundAccessGroup;
            }
        }

        return null;
    }

    /**
     * Return all unique access group id from request.
     *
     * @param array<array{claim_value: string, access_group_id: int}> $authorizationRulesFromRequest
     *
     * @return int[]
     */
    private function getAccessGroupIds(array $authorizationRulesFromRequest): array
    {
        $accessGroupIds = [];
        foreach ($authorizationRulesFromRequest as $authorizationRules) {
            $accessGroupIds[] = $authorizationRules['access_group_id'];
        }

        return array_unique($accessGroupIds);
    }

    /**
     * Create Authentication Condition from request data.
     *
     * @param array{
     *  "is_enabled": bool,
     *  "attribute_path": string,
     *  "authorized_values": string[],
     *  "trusted_client_addresses": string[],
     *  "blacklist_client_addresses": string[],
     *  "endpoint": array{
     *      "type": string,
     *      "custom_endpoint":string|null
     *  }
     * } $authenticationConditionsParameters
     *
     * @throws AssertionFailedException
     * @throws ConfigurationException
     *
     * @return AuthenticationConditions
     */
    private function createAuthenticationConditions(array $authenticationConditionsParameters): AuthenticationConditions
    {
        $authenticationConditions = new AuthenticationConditions(
            $authenticationConditionsParameters['is_enabled'],
            $authenticationConditionsParameters['attribute_path'],
            new Endpoint(
                $authenticationConditionsParameters['endpoint']['type'],
                $authenticationConditionsParameters['endpoint']['custom_endpoint']
            ),
            $authenticationConditionsParameters['authorized_values'],
        );
        $authenticationConditions->setTrustedClientAddresses(
            $authenticationConditionsParameters['trusted_client_addresses']
        );
        $authenticationConditions->setBlacklistClientAddresses(
            $authenticationConditionsParameters['blacklist_client_addresses']
        );

        return $authenticationConditions;
    }

    /**
     * Create Groups Mapping from data send to the request.
     *
     * @param array{
     *  "is_enabled": bool,
     *  "attribute_path": string,
     *  "endpoint": array{
     *      "type": string,
     *      "custom_endpoint":string|null
     *  },
     *  "relations":array<array{
     *      "group_value": string,
     *      "contact_group_id": int
     *  }>
     * } $groupsMappingParameters
     *
     * @return GroupsMapping
     */
    private function createGroupsMapping(array $groupsMappingParameters): GroupsMapping
    {
        $contactGroupIds = $this->getContactGroupIds($groupsMappingParameters['relations']);
        $foundContactGroups = $this->contactGroupRepository->findByIds($contactGroupIds);
        $this->logNonExistentContactGroupsIds($contactGroupIds, $foundContactGroups);
        $contactGroupRelations = [];
        foreach ($groupsMappingParameters['relations'] as $contactGroupRelation) {
            $contactGroup = $this->findContactGroupFromFoundcontactGroups(
                $contactGroupRelation['contact_group_id'],
                $foundContactGroups
            );
            if ($contactGroup !== null) {
                $contactGroupRelations[] = new ContactGroupRelation(
                    $contactGroupRelation['group_value'],
                    $contactGroup
                );
            }
        }
        $endpoint = new Endpoint(
            $groupsMappingParameters['endpoint']['type'],
            $groupsMappingParameters['endpoint']['custom_endpoint']
        );

        return new GroupsMapping(
            $groupsMappingParameters['is_enabled'],
            $groupsMappingParameters['attribute_path'],
            $endpoint,
            $contactGroupRelations
        );
    }

    /**
     * @param array<array{"group_value": string, "contact_group_id": int}> $contactGroupParameters
     *
     * @return int[]
     */
    private function getContactGroupIds(array $contactGroupParameters): array
    {
        $contactGroupIds = [];
        foreach ($contactGroupParameters as $groupsMapping) {
            $contactGroupIds[] = $groupsMapping['contact_group_id'];
        }

        return array_unique($contactGroupIds);
    }

    /**
     * Add log for all the non existent contact groups.
     *
     * @param int[] $contactGroupIds
     * @param ContactGroup[] $foundContactGroups
     */
    private function logNonExistentContactGroupsIds(array $contactGroupIds, array $foundContactGroups): void
    {
        $foundContactGroupsId = [];
        foreach ($foundContactGroups as $foundAccessGroup) {
            $foundContactGroupsId[] = $foundAccessGroup->getId();
        }
        $nonExistentContactGroupsIds = array_diff($contactGroupIds, $foundContactGroupsId);

        if (! empty($nonExistentContactGroupsIds)) {
            $this->error('Contact groups not found', [
                'contact_group_ids' => implode(', ', $nonExistentContactGroupsIds),
            ]);
        }
    }

    /**
     * Compare the contact group id sent in request with contact groups from database
     * Return the contact group that have the same id than the contact group id from the request.
     *
     * @param int $contactGroupIdFromRequest contact group id sent in the request
     * @param ContactGroup[] $foundContactGroups contact groups found in data storage
     *
     * @return ContactGroup|null
     */
    private function findContactGroupFromFoundcontactGroups(
        int $contactGroupIdFromRequest,
        array $foundContactGroups
    ): ?ContactGroup {
        foreach ($foundContactGroups as $foundContactGroup) {
            if ($contactGroupIdFromRequest === $foundContactGroup->getId()) {
                return $foundContactGroup;
            }
        }

        return null;
    }
}
