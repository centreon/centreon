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

namespace Core\Security\ProviderConfiguration\Infrastructure\SAML\Repository;

use Adaptation\Database\Connection\Collection\BatchInsertParameters;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Database\QueryBuilder\Exception\QueryBuilderException;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Security\ProviderConfiguration\Application\SAML\Repository\WriteSAMLConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Domain\Model\ACLConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthenticationConditions;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\GroupsMapping;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration;

class DbWriteSAMLConfigurationRepository extends DatabaseRepository implements WriteSAMLConfigurationRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function updateConfiguration(Configuration $configuration): void
    {
        try {
            $customConfiguration = json_encode(
                $this->buildCustomConfigurationFromSAMLConfiguration($configuration),
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            throw new RepositoryException(
                message: 'Could not encode SAML custom configuration to JSON: ' . $e->getMessage(),
                context: ['configuration' => $configuration],
                previous: $e
            );
        }

        try {
            $query = $this->connection->createQueryBuilder()
                ->update('`:db`.`provider_configuration`')
                ->set('`custom_configuration`', ':customConfiguration')
                ->set('`is_active`', ':isActive')
                ->set('`is_forced`', ':isForced')
                ->where('`name` = :name')
                ->getQuery();

            $queryParameters = QueryParameters::create(
                [
                    QueryParameter::string('customConfiguration', $customConfiguration),
                    QueryParameter::bool('isActive', $configuration->isActive()),
                    QueryParameter::bool('isForced', $configuration->isForced()),
                    QueryParameter::string('name', Provider::SAML),
                ]
            );

            $this->connection->update($this->translateDbName($query), $queryParameters);
        } catch (QueryBuilderException|ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                message: 'Could not update SAML configuration: ' . $e->getMessage(),
                context: ['configuration' => $configuration],
                previous: $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteAuthorizationRules(): void
    {
        try {
            $query = $this->connection->createQueryBuilder()
                ->select('id')
                ->from('`:db`.`provider_configuration`')
                ->where('`name` = :name')
                ->getQuery();

            $queryParameters = QueryParameters::create([QueryParameter::string('name', Provider::SAML)]);
            $providerConfigurationId = $this->connection->fetchOne($this->translateDbName($query), $queryParameters);

            $query = $this->connection->createQueryBuilder()
                ->delete('`:db`.`security_provider_access_group_relation`')
                ->where('`provider_configuration_id` = :providerConfigurationId')
                ->getQuery();

            $queryParameters = QueryParameters::create([QueryParameter::int('providerConfigurationId', $providerConfigurationId)]);
            $this->connection->delete($this->translateDbName($query), $queryParameters);
        } catch (QueryBuilderException|ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                message: 'Could not delete authorization rules for SAML configuration: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function insertAuthorizationRules(array $authorizationRules): void
    {
        if ($authorizationRules === []) {
            throw new RepositoryException(
                message: 'No authorization rules to insert for SAML configuration.',
                context: ['authorizationRules' => $authorizationRules]
            );
        }

        try {
            $query = $this->connection->createQueryBuilder()
                ->select('id')
                ->from('`:db`.`provider_configuration`')
                ->where('`name` = :name')
                ->getQuery();

            $queryParameters = QueryParameters::create([QueryParameter::string('name', Provider::SAML)]);
            $providerConfigurationId = $this->connection->fetchOne($this->translateDbName($query), $queryParameters);

            $batchInsertParameters = new BatchInsertParameters();
            foreach ($authorizationRules as $index => $authorizationRule) {
                $batchInsertParameters->add(
                    $index,
                    QueryParameters::create([
                        QueryParameter::string('claimValue', $authorizationRule->getClaimValue()),
                        QueryParameter::int('accessGroupId', $authorizationRule->getAccessGroup()->getId()),
                        QueryParameter::int('providerConfigurationId', $providerConfigurationId),
                        QueryParameter::int('priority', $authorizationRule->getPriority()),
                    ])
                );
            }

            $this->connection->batchInsert(
                $this->translateDbName('`:db`.`security_provider_access_group_relation`'),
                ['claim_value', 'access_group_id', 'provider_configuration_id', 'priority'],
                $batchInsertParameters
            );
        } catch (QueryBuilderException|ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                message: 'Could not insert authorization rules for SAML configuration: ' . $e->getMessage(),
                context: ['authorizationRules' => $authorizationRules],
                previous: $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteContactGroupRelations(): void
    {
        try {
            $query = $this->connection->createQueryBuilder()
                ->select('id')
                ->from('`:db`.`provider_configuration`')
                ->where('`name` = :name')
                ->getQuery();

            $queryParameters = QueryParameters::create([QueryParameter::string('name', Provider::SAML)]);
            $providerConfigurationId = $this->connection->fetchOne($this->translateDbName($query), $queryParameters);

            $query = $this->connection->createQueryBuilder()
                ->delete('`:db`.`security_provider_contact_group_relation`')
                ->where('`provider_configuration_id` = :providerConfigurationId')
                ->getQuery();

            $queryParameters = QueryParameters::create([QueryParameter::int('providerConfigurationId', $providerConfigurationId)]);
            $this->connection->delete($this->translateDbName($query), $queryParameters);
        } catch (QueryBuilderException|ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                message: 'Could not delete contact group relations:' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function insertContactGroupRelations(array $contactGroupRelations): void
    {
        if ($contactGroupRelations === []) {
            throw new RepositoryException(
                message: 'No contact group relations to insert for SAML configuration.',
                context: ['contactGroupRelations' => $contactGroupRelations]
            );
        }

        try {
            $query = $this->connection->createQueryBuilder()
                ->select('id')
                ->from('`:db`.`provider_configuration`')
                ->where('`name` = :name')
                ->getQuery();

            $queryParameters = QueryParameters::create([QueryParameter::string('name', Provider::SAML)]);
            $providerConfigurationId = $this->connection->fetchOne($this->translateDbName($query), $queryParameters);

            $batchInsertParameters = new BatchInsertParameters();
            foreach ($contactGroupRelations as $index => $contactGroupRelation) {
                $batchInsertParameters->add(
                    $index,
                    QueryParameters::create([
                        QueryParameter::string('claimValue', $contactGroupRelation->getClaimValue()),
                        QueryParameter::int('contactGroupId', $contactGroupRelation->getContactGroup()->getId()),
                        QueryParameter::int('providerConfigurationId', $providerConfigurationId),
                    ])
                );
            }

            $this->connection->batchInsert(
                $this->translateDbName('`:db`.`security_provider_contact_group_relation`'),
                ['claim_value', 'contact_group_id', 'provider_configuration_id'],
                $batchInsertParameters
            );
        } catch (QueryBuilderException|ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                message: 'Could not insert contact group relations for SAML configuration: ' . $e->getMessage(),
                context: ['contactGroupRelations' => $contactGroupRelations],
                previous: $e
            );
        }
    }

    /**
     * Format SAMLConfiguration for custom_configuration.
     *
     * @param Configuration $configuration
     *
     * @return array<string, mixed>
     */
    private function buildCustomConfigurationFromSAMLConfiguration(Configuration $configuration): array
    {
        /** @var CustomConfiguration $customConfiguration */
        $customConfiguration = $configuration->getCustomConfiguration();

        return [
            'remote_login_url' => $customConfiguration->getRemoteLoginUrl(),
            'entity_id_url' => $customConfiguration->getEntityIDUrl(),
            'certificate' => $customConfiguration->getPublicCertificate(),
            'user_id_attribute' => $customConfiguration->getUserIdAttribute(),
            'requested_authn_context' => $customConfiguration->hasRequestedAuthnContext(),
            'requested_authn_context_comparison' => $customConfiguration->getRequestedAuthnContextComparison()->value,
            'logout_from' => $customConfiguration->getLogoutFrom(),
            'logout_from_url' => $customConfiguration->getLogoutFromUrl(),
            'auto_import' => $customConfiguration->isAutoImportEnabled(),
            'contact_template_id' => $customConfiguration->getContactTemplate()?->getId(),
            'email_bind_attribute' => $customConfiguration->getEmailBindAttribute(),
            'fullname_bind_attribute' => $customConfiguration->getUserNameBindAttribute(),
            'roles_mapping' => $this->aclConditionsToArray($customConfiguration->getACLConditions()),
            'authentication_conditions' => $this->authenticationConditionsToArray(
                $customConfiguration->getAuthenticationConditions()
            ),
            'groups_mapping' => $this->groupsMappingToArray(
                $customConfiguration->getGroupsMapping()
            ),
        ];
    }

    /**
     * @param AuthenticationConditions $authenticationConditions
     *
     * @return array<string,array<string|null>|bool|string>
     */
    private function authenticationConditionsToArray(AuthenticationConditions $authenticationConditions): array
    {
        $conditionsSettings = [
            'is_enabled' => $authenticationConditions->isEnabled(),
            'attribute_path' => $authenticationConditions->getAttributePath(),
            'authorized_values' => $authenticationConditions->getAuthorizedValues(),
            'trusted_client_addresses' => $authenticationConditions->getTrustedClientAddresses(),
            'blacklist_client_addresses' => $authenticationConditions->getBlacklistClientAddresses(),
        ];

        $endpoint = $authenticationConditions->getEndpoint();
        if ($endpoint) {
            $conditionsSettings['endpoint'] = $endpoint->toArray();
        }

        return $conditionsSettings;
    }

    /**
     * @param GroupsMapping $groupsMapping
     *
     * @return array<string,bool|string|array<string,string|null>>
     */
    private function groupsMappingToArray(GroupsMapping $groupsMapping): array
    {
        $groupsSettings = [
            'is_enabled' => $groupsMapping->isEnabled(),
            'attribute_path' => $groupsMapping->getAttributePath(),
        ];

        $endpoint = $groupsMapping->getEndpoint();
        if ($endpoint) {
            $groupsSettings['endpoint'] = $endpoint->toArray();
        }

        return $groupsSettings;
    }

    /**
     * @param ACLConditions $aclConditions
     *
     * @return array<string,bool|string|array<string,string|null>>
     */
    private function aclConditionsToArray(ACLConditions $aclConditions): array
    {
        $rolesSettings = [
            'is_enabled' => $aclConditions->isEnabled(),
            'apply_only_first_role' => $aclConditions->onlyFirstRoleIsApplied(),
            'attribute_path' => $aclConditions->getAttributePath(),
        ];

        $endpoint = $aclConditions->getEndpoint();
        if ($endpoint) {
            $rolesSettings['endpoint'] = $endpoint->toArray();
        }

        return $rolesSettings;
    }
}
