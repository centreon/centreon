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

namespace Core\Security\ProviderConfiguration\Application\OpenId\UseCase\PartialUpdateOpenIdConfiguration;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Application\Type\NoValue;
use Core\Common\Application\UseCase\VaultTrait;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactTemplateRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Contact\Domain\Model\ContactTemplate;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Security\Authentication\Application\Provider\ProviderAuthenticationFactoryInterface;
use Core\Security\ProviderConfiguration\Application\OpenId\Repository\WriteOpenIdConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Domain\CustomConfigurationInterface;
use Core\Security\ProviderConfiguration\Domain\Exception\ConfigurationException;
use Core\Security\ProviderConfiguration\Domain\Model\ACLConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthenticationConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthorizationRule;
use Core\Security\ProviderConfiguration\Domain\Model\ContactGroupRelation;
use Core\Security\ProviderConfiguration\Domain\Model\Endpoint;
use Core\Security\ProviderConfiguration\Domain\Model\GroupsMapping;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\OpenId\Model\CustomConfiguration;
use Core\Security\Vault\Application\Repository\ReadVaultConfigurationRepositoryInterface;
use Core\Security\Vault\Domain\Model\VaultConfiguration;

/**
 * @phpstan-import-type _RoleMapping from PartialUpdateOpenIdConfigurationRequest
 * @phpstan-import-type _GroupsMapping from PartialUpdateOpenIdConfigurationRequest
 * @phpstan-import-type _AuthConditions from PartialUpdateOpenIdConfigurationRequest
 * @phpstan-import-type _PartialUpdateOpenIdConfigurationRequest from PartialUpdateOpenIdConfigurationRequest
 *
 * @phpstan-type _CustomConfigurationAsArray array<string, mixed>
 */
final class PartialUpdateOpenIdConfiguration
{
    use LoggerTrait, VaultTrait;

    /**
     * @param WriteOpenIdConfigurationRepositoryInterface $repository
     * @param ReadContactTemplateRepositoryInterface $contactTemplateRepository
     * @param ReadContactGroupRepositoryInterface $contactGroupRepository
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param ProviderAuthenticationFactoryInterface $providerAuthenticationFactory
     * @param ReadVaultConfigurationRepositoryInterface $vaultConfigurationRepository
     * @param WriteVaultRepositoryInterface $writeVaultRepository
     */
    public function __construct(
        private WriteOpenIdConfigurationRepositoryInterface $repository,
        private ReadContactTemplateRepositoryInterface $contactTemplateRepository,
        private ReadContactGroupRepositoryInterface $contactGroupRepository,
        private ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private ProviderAuthenticationFactoryInterface $providerAuthenticationFactory,
        private ReadVaultConfigurationRepositoryInterface $vaultConfigurationRepository,
        private WriteVaultRepositoryInterface $writeVaultRepository
    ) {
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::OPEN_ID_CREDENTIALS_VAULT_PATH);
    }

    /**
     * @param PartialUpdateOpenIdConfigurationPresenterInterface $presenter
     * @param PartialUpdateOpenIdConfigurationRequest $request
     */
    public function __invoke(
        PartialUpdateOpenIdConfigurationPresenterInterface $presenter,
        PartialUpdateOpenIdConfigurationRequest $request
    ): void {

        $this->info('Partially Updating OpenID Provider');
        try {
            $provider = $this->providerAuthenticationFactory->create(Provider::OPENID);
            /** @var Configuration */
            $configuration = $provider->getConfiguration();

            $customConfiguration = $this->createUpdatedCustomConfiguration(
                $configuration->getCustomConfiguration(),
                $request
            );

            $configuration->update(
                isActive: $request->isActive instanceOf NoValue
                    ? $configuration->isActive()
                    : $request->isActive,
                isForced: $request->isForced instanceOf NoValue
                    ? $configuration->isForced()
                    : $request->isForced
            );
            $configuration->setCustomConfiguration($customConfiguration);

            $this->repository->updateConfiguration($configuration);
        } catch (AssertionException|AssertionFailedException|ConfigurationException $ex) {
            $this->error(
                'Unable to perform partial update on OpenID Provider because one or several parameters are invalid',
                ['trace' => $ex->getTraceAsString()]
            );
            $presenter->setResponseStatus(new ErrorResponse($ex->getMessage()));

            return;
        } catch (\Throwable $ex) {
            $this->error('Error during Opend ID Provider Partial Update', ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new PartialUpdateOpenIdConfigurationErrorResponse());

            return;
        }

        $presenter->setResponseStatus(new NoContentResponse());
    }

    /**
     * @param CustomConfiguration $customConfig
     * @param PartialUpdateOpenIdConfigurationRequest $request
     *
     * @return CustomConfiguration
     */
    private function createUpdatedCustomConfiguration(
        CustomConfigurationInterface $customConfig,
        PartialUpdateOpenIdConfigurationRequest $request
    ): CustomConfiguration {

        $requestArray = $request->toArray();

        /** @var CustomConfiguration $customConfig */
        $oldConfigArray = $customConfig->toArray();

        $customConfigurationAsArray = [];

        foreach ($requestArray as $paramName => $paramValue) {
            if ($paramName === 'contact_template') {
                $customConfigurationAsArray['contact_template']
                    = $paramValue instanceof NoValue
                    ? $customConfig->getContactTemplate()
                    : (
                    $paramValue && array_key_exists('id', $paramValue) !== null
                        ? $this->getContactTemplateOrFail($paramValue)
                        : null
                    );
            } elseif ($paramName === 'roles_mapping') {
                $customConfigurationAsArray['roles_mapping'] = $this->createUpdatedAclConditions(
                    $paramValue,
                    $customConfig->getACLConditions()
                );
            } elseif ($paramName === 'authentication_conditions') {
                $customConfigurationAsArray['authentication_conditions'] = $this->createUpdatedAuthenticationConditions(
                    $paramValue,
                    $customConfig->getAuthenticationConditions()
                );
            } elseif ($paramName === 'groups_mapping') {
                $customConfigurationAsArray['groups_mapping'] = $this->createUpdatedGroupsMapping(
                    $paramValue,
                    $customConfig->getGroupsMapping()
                );
            } elseif ($paramValue instanceOf NoValue) {
                $customConfigurationAsArray[$paramName] = $oldConfigArray[$paramName];
            } else {
                $customConfigurationAsArray[$paramName] = $requestArray[$paramName];
            }
        }

        $customConfigurationAsArray = $this->manageClientIdAndClientSecretIntoVault(
            $customConfigurationAsArray,
            $customConfig
        );

        return new CustomConfiguration($customConfigurationAsArray);
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
     * @param _RoleMapping $paramValue
     * @param ACLConditions $aclConditions
     *
     * @return ACLConditions
     */
    private function createUpdatedAclConditions(array $paramValue, ACLConditions $aclConditions): ACLConditions
    {
        return new ACLConditions(
            isEnabled: $paramValue['is_enabled'] instanceOf NoValue
                ? $aclConditions->isEnabled()
                : $paramValue['is_enabled'],
            applyOnlyFirstRole: $paramValue['apply_only_first_role'] instanceOf NoValue
                ? $aclConditions->onlyFirstRoleIsApplied()
                : $paramValue['apply_only_first_role'],
            attributePath: $paramValue['attribute_path'] instanceOf NoValue
                ? $aclConditions->getAttributePath()
                : $paramValue['attribute_path'],
            endpoint: $paramValue['endpoint'] instanceof NoValue
                ? $aclConditions->getEndpoint()
                : new Endpoint(
                    $paramValue['endpoint']['type'],
                    $paramValue['endpoint']['custom_endpoint']
                ),
            relations: $paramValue['relations'] instanceOf NoValue
                ? $aclConditions->getRelations()
                : $this->createAuthorizationRules($paramValue['relations']),
        );
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
     * @param _AuthConditions $requestParam
     * @param AuthenticationConditions $authConditions
     *
     * @return AuthenticationConditions
     */
    private function createUpdatedAuthenticationConditions(
        array $requestParam,
        AuthenticationConditions $authConditions
    ): AuthenticationConditions {
        $newAuthConditions = new AuthenticationConditions(
            isEnabled: $requestParam['is_enabled'] instanceOf NoValue
                ? $authConditions->isEnabled()
                : $requestParam['is_enabled'],
            attributePath: $requestParam['attribute_path'] instanceOf NoValue
                ? $authConditions->getAttributePath()
                : $requestParam['attribute_path'],
            endpoint: $requestParam['endpoint'] instanceOf NoValue
                ? $authConditions->getEndpoint()
                : new Endpoint(
                    $requestParam['endpoint']['type'],
                    $requestParam['endpoint']['custom_endpoint']
                ),
            authorizedValues: $requestParam['authorized_values'] instanceOf NoValue
                ? $authConditions->getAuthorizedValues()
                : $requestParam['authorized_values'],
        );
        $newAuthConditions->setTrustedClientAddresses(
            $requestParam['trusted_client_addresses'] instanceOf NoValue
                ? $authConditions->getTrustedClientAddresses()
                : $requestParam['trusted_client_addresses']
        );
        $newAuthConditions->setBlacklistClientAddresses(
            $requestParam['blacklist_client_addresses'] instanceOf NoValue
                ? $authConditions->getBlacklistClientAddresses()
                : $requestParam['blacklist_client_addresses']
        );

        return $newAuthConditions;
    }

    /**
     * @param _GroupsMapping $requestParam
     * @param GroupsMapping $groupsMapping
     *
     * @return GroupsMapping
     */
    private function createUpdatedGroupsMapping(
        array $requestParam,
        GroupsMapping $groupsMapping
    ): GroupsMapping {
        $contactGroupRelations = [];
        if (! $requestParam['relations'] instanceOf NoValue) {
            $contactGroupIds = $this->getContactGroupIds($requestParam['relations']);
            $foundContactGroups = $this->contactGroupRepository->findByIds($contactGroupIds);
            $this->logNonExistentContactGroupsIds($contactGroupIds, $foundContactGroups);
            foreach ($requestParam['relations'] as $contactGroupRelation) {
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
        }

        return new GroupsMapping(
            isEnabled: $requestParam['is_enabled'] instanceOf NoValue
                ? $groupsMapping->isEnabled()
                : $requestParam['is_enabled'],
            attributePath: $requestParam['attribute_path'] instanceOf NoValue
                ? $groupsMapping->getAttributePath()
                : $requestParam['attribute_path'],
            endpoint: $requestParam['endpoint'] instanceOf NoValue
                ? $groupsMapping->getEndpoint()
                : new Endpoint(
                    $requestParam['endpoint']['type'],
                    $requestParam['endpoint']['custom_endpoint']
                ),
            contactGroupRelations: $requestParam['relations'] instanceOf NoValue
                ? $groupsMapping->getContactGroupRelations()
                : $contactGroupRelations
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

    /**
     * Manage the client id and client secret into the vault.
     * This method will upsert the client id and the client secret if one of those values change and are not already
     * stored into the vault.
     *
     * @param _CustomConfigurationAsArray $requestArray
     * @param CustomConfiguration $customConfiguration
     *
     * @throws \Throwable
     *
     * @return _CustomConfigurationAsArray
     */
    private function manageClientIdAndClientSecretIntoVault(
        array $requestArray,
        CustomConfiguration $customConfiguration
    ): array {
        // No need to do anything if vault is not configured
        if (! $this->vaultConfigurationRepository->exists()) {
            return $requestArray;
        }

        // Retrieve the uuid from the vault path if the client id or client secret is already stored
        $uuid = null;
        $clientId = $customConfiguration->getClientId();
        $clientSecret = $customConfiguration->getClientSecret();

        if ($clientId !== null && str_starts_with($clientId, VaultConfiguration::VAULT_PATH_PATTERN)) {
            $uuid = $this->getUuidFromPath($clientId);
        } elseif ($clientSecret !== null && str_starts_with($clientSecret, VaultConfiguration::VAULT_PATH_PATTERN)) {
            $uuid = $this->getUuidFromPath($clientSecret);
        }

        // Update the vault with the new client id and client secret if the value are not a vault path.
        $data = [];
        if (
            $requestArray['client_id'] !== null
            && ! str_starts_with((string) $requestArray['client_id'], VaultConfiguration::VAULT_PATH_PATTERN)
        ) {
            $data[VaultConfiguration::OPENID_CLIENT_ID_KEY] = $requestArray['client_id'];
        }
        if (
            $requestArray['client_secret'] !== null
            && ! str_starts_with((string) $requestArray['client_secret'], VaultConfiguration::VAULT_PATH_PATTERN)
        ) {
            $data[VaultConfiguration::OPENID_CLIENT_SECRET_KEY] = $requestArray['client_secret'];
        }

        if (! empty($data)) {
            $vaultPaths = $this->writeVaultRepository->upsert(
                $uuid,
                $data
            );

            // Assign new values to the request array
            $requestArray['client_id'] = $vaultPaths[VaultConfiguration::OPENID_CLIENT_ID_KEY];
            $requestArray['client_secret'] = $vaultPaths[VaultConfiguration::OPENID_CLIENT_SECRET_KEY];
        }

        return $requestArray;
    }
}
