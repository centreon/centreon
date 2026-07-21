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

use Centreon\Domain\Log\Logger;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Infrastructure\Repository\DatabaseRepositoryManager;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactTemplateRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Contact\Domain\Model\ContactTemplate;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\ProviderConfiguration\Application\SAML\Repository\WriteSAMLConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Domain\Exception\ConfigurationException;
use Core\Security\ProviderConfiguration\Domain\Model\ACLConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthenticationConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthorizationRule;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\ContactGroupRelation;
use Core\Security\ProviderConfiguration\Domain\Model\GroupsMapping;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\OpenId\Exceptions\ACLConditionsException;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration;

final readonly class UpdateSAMLConfiguration
{
    public function __construct(
        private WriteSAMLConfigurationRepositoryInterface $writeSAMLConfigurationRepository,
        private ReadContactTemplateRepositoryInterface $contactTemplateRepository,
        private ReadContactGroupRepositoryInterface $contactGroupRepository,
        private ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private DatabaseRepositoryManager $databaseRepositoryManager,
        private ProviderAuthenticationFactoryInterface $providerAuthenticationFactory,
    ) {
    }

    public function __invoke(
        UpdateSAMLConfigurationPresenterInterface $presenter,
        UpdateSAMLConfigurationRequest $request,
    ): void {
        try {
            $provider = $this->providerAuthenticationFactory->create(Provider::SAML);
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
            $configuration->setCustomConfiguration(CustomConfiguration::createFromValues($requestArray));
            $this->updateConfiguration($configuration);
            $presenter->presentResponse(new NoContentResponse());
        } catch (\Throwable $e) {
            $presenter->presentResponse(new ErrorResponse(
                message: $e->getMessage(),
                context: ['provider' => Provider::SAML],
                exception: $e
            ));

            return;
        }

        $presenter->presentResponse(new NoContentResponse());
    }

    /**
     * Get Contact template or throw an Exception.
     *
     * @param array{id: int, name: string}|null $contactTemplateFromRequest
     *
     * @throws \Throwable|ConfigurationException
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
     * @throws RepositoryException
     *
     * @return AuthorizationRule[]
     */
    private function createAuthorizationRules(array $authorizationRulesFromRequest): array
    {
        $accessGroupIds = $this->getAccessGroupIds($authorizationRulesFromRequest);

        if ($accessGroupIds === []) {
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
     * @param array{
     *      is_enabled: bool,
     *      apply_only_first_role: bool,
     *      attribute_path: string,
     *      relations: array<array{claim_value: string, access_group_id: int, priority: int}>
     *  } $rolesMapping
     *
     * @throws RepositoryException
     * @throws ACLConditionsException
     */
    private function createAclConditions(array $rolesMapping): ACLConditions
    {
        $rules = $this->createAuthorizationRules($rolesMapping['relations']);

        return new ACLConditions(
            $rolesMapping['is_enabled'],
            $rolesMapping['apply_only_first_role'],
            $rolesMapping['attribute_path'],
            null,
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

        if ($nonExistentAccessGroupsIds !== []) {
            Logger::create()->error('Access Groups not found', [
                'access_group_ids' => implode(', ', $nonExistentAccessGroupsIds),
            ]);
        }
    }

    /**
     * Compare the access group id sent in request with Access groups from database
     * Return the access group that have the same id than the access group id from the request.
     *
     * @param AccessGroup[] $foundAccessGroups Access groups found in data storage
     */
    private function findAccessGroupFromFoundAccessGroups(
        int $accessGroupIdFromRequest,
        array $foundAccessGroups,
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
     * Update SAML Provider.
     *
     * @throws RepositoryException
     */
    private function updateConfiguration(Configuration $configuration): void
    {
        $isAlreadyInTransaction = $this->databaseRepositoryManager->isTransactionActive();
        try {
            if (! $isAlreadyInTransaction) {
                $this->databaseRepositoryManager->startTransaction();
            }
            $this->writeSAMLConfigurationRepository->updateConfiguration($configuration);

            /** @var CustomConfiguration $customConfiguration */
            $customConfiguration = $configuration->getCustomConfiguration();
            $authorizationRules = $customConfiguration->getACLConditions()->getRelations();

            $this->writeSAMLConfigurationRepository->deleteAuthorizationRules();
            if ($authorizationRules !== []) {
                $this->writeSAMLConfigurationRepository->insertAuthorizationRules($authorizationRules);
            }

            $contactGroupRelations = $customConfiguration->getGroupsMapping()->getContactGroupRelations();

            $this->writeSAMLConfigurationRepository->deleteContactGroupRelations();
            if ($contactGroupRelations !== []) {
                $this->writeSAMLConfigurationRepository->insertContactGroupRelations($contactGroupRelations);
            }

            if (! $isAlreadyInTransaction) {
                $this->databaseRepositoryManager->commitTransaction();
            }
        } catch (RepositoryException $ex) {
            if (! $isAlreadyInTransaction) {
                $this->databaseRepositoryManager->rollbackTransaction();

                throw $ex;
            }
        }
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
     * } $authenticationConditionsParameters
     *
     * @throws ConfigurationException
     */
    private function createAuthenticationConditions(array $authenticationConditionsParameters): AuthenticationConditions
    {
        return new AuthenticationConditions(
            $authenticationConditionsParameters['is_enabled'],
            $authenticationConditionsParameters['attribute_path'],
            null,
            $authenticationConditionsParameters['authorized_values'],
        );
    }

    /**
     * Create Groups Mapping from data send to the request.
     *
     * @param array{
     *  "is_enabled": bool,
     *  "attribute_path": string,
     *  "relations":array<array{
     *      "group_value": string,
     *      "contact_group_id": int
     *  }>
     * } $groupsMappingParameters
     *
     * @throws \Throwable
     */
    private function createGroupsMapping(array $groupsMappingParameters): GroupsMapping
    {
        $contactGroupIds = $this->getContactGroupIds($groupsMappingParameters['relations']);
        $foundContactGroups = $this->contactGroupRepository->findByIds($contactGroupIds);
        $this->logNonExistentContactGroupsIds($contactGroupIds, $foundContactGroups);
        $contactGroupRelations = [];
        foreach ($groupsMappingParameters['relations'] as $contactGroupRelation) {
            $contactGroup = $this->findContactGroupFromFoundContactGroups(
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

        return new GroupsMapping(
            $groupsMappingParameters['is_enabled'],
            $groupsMappingParameters['attribute_path'],
            null,
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

        if ($nonExistentContactGroupsIds !== []) {
            Logger::create()->error('Contact groups not found', [
                'contact_group_ids' => implode(', ', $nonExistentContactGroupsIds),
            ]);
        }
    }

    /**
     * Compare the contact group id sent in request with contact groups from database
     * Return the contact group that have the same id than the contact group id from the request.
     *
     * @param ContactGroup[] $foundContactGroups contact groups found in data storage
     */
    private function findContactGroupFromFoundContactGroups(
        int $contactGroupIdFromRequest,
        array $foundContactGroups,
    ): ?ContactGroup {
        foreach ($foundContactGroups as $foundContactGroup) {
            if ($contactGroupIdFromRequest === $foundContactGroup->getId()) {
                return $foundContactGroup;
            }
        }

        return null;
    }
}
