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
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Core\Security\ProviderConfiguration\Infrastructure\SAML\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Security\ProviderConfiguration\Application\SAML\Repository\WriteSAMLConfigurationRepositoryInterface
    as WriteRepositoryInterface;
use Core\Security\ProviderConfiguration\Domain\Model\Configuration;
use Core\Security\ProviderConfiguration\Domain\Model\Provider;
use Core\Security\ProviderConfiguration\Domain\Model\ACLConditions;
use Core\Security\ProviderConfiguration\Domain\Model\AuthenticationConditions;
use Core\Security\ProviderConfiguration\Domain\SAML\Model\CustomConfiguration;
use Core\Security\ProviderConfiguration\Domain\Model\GroupsMapping;
use Core\Security\ProviderConfiguration\Domain\Model\ContactGroupRelation;

class DbWriteSAMLConfigurationRepository extends AbstractRepositoryDRB implements WriteRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function updateConfiguration(Configuration $configuration): void
    {
        $this->info('Updating SAML Provider in DBMS');
        $statement = $this->db->prepare(
            $this->translateDbName(
                "UPDATE `:db`.`provider_configuration` SET
                `custom_configuration` = :customConfiguration, `is_active` = :isActive, `is_forced` = :isForced
                WHERE `name`= :name"
            )
        );

        $statement->bindValue(
            ':customConfiguration',
            json_encode($this->buildCustomConfigurationFromSAMLConfiguration($configuration))
        );
        $statement->bindValue(':name', Provider::SAML);
        $statement->bindValue(':isActive', $configuration->isActive() ? '1' : '0');
        $statement->bindValue(':isForced', $configuration->isForced() ? '1' : '0');
        $statement->execute();

        /** @var CustomConfiguration $customConfiguration */
        $customConfiguration = $configuration->getCustomConfiguration();
        $authorizationRules = $customConfiguration->getACLConditions()->getRelations();

        $this->info('Removing existing authorization rules');
        $this->deleteAuthorizationRules();
        $this->info('Inserting new authorization rules');
        $this->insertAuthorizationRules($authorizationRules);

        $contactGroupRelations = $customConfiguration->getGroupsMapping()->getContactGroupRelations();

        $this->info('Removing existing group mappings');
        $this->deleteContactGroupRelations();
        $this->info('Inserting new group mappings');
        $this->insertContactGroupRelations($contactGroupRelations);
    }

    /**
     * Format SAMLConfiguration for custom_configuration.
     *
     * @param Configuration $configuration
     * @return array<string, mixed>
     */
    private function buildCustomConfigurationFromSAMLConfiguration(Configuration $configuration): array
    {
        /** @var CustomConfiguration $customConfiguration */
        $customConfiguration = $configuration->getCustomConfiguration();

        return [
            'is_active' => $configuration->isActive(),
            'is_forced' => $configuration->isForced(),
            'remote_login_url' => $customConfiguration->getRemoteLoginUrl(),
            'entity_id_url' => $customConfiguration->getEntityIDUrl(),
            'certificate' => $customConfiguration->getPublicCertificate(),
            'user_id_attribute' => $customConfiguration->getUserIdAttribute(),
            'logout_from' => $customConfiguration->getLogoutFrom(),
            'logout_from_url' => $customConfiguration->getLogoutFromUrl(),
            'auto_import' => $customConfiguration->isAutoImportEnabled(),
            'contact_template_id' => $customConfiguration->getContactTemplate()?->getId(),
            'email_bind_attribute' => $customConfiguration->getEmailBindAttribute(),
            'fullname_bind_attribute' => $customConfiguration->getUserNameBindAttribute(),
            'roles_mapping' => $this->aclConditionsToArray($customConfiguration->getACLConditions()),
            "authentication_conditions" => $this->authenticationConditionsToArray(
                $customConfiguration->getAuthenticationConditions()
            ),
            "groups_mapping" => $this->groupsMappingToArray(
                $customConfiguration->getGroupsMapping()
            )
        ];
    }

    /**
     * @inheritDoc
     */
    public function deleteAuthorizationRules(): void
    {
        $query = sprintf("SELECT id FROM provider_configuration WHERE name='%s'", Provider::SAML);
        $statement = $this->db->query($query);
        if ($statement !== false && ($result = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            $providerConfigurationId = (int)$result['id'];
            $deleteStatement = $this->db->prepare(
                "DELETE FROM security_provider_access_group_relation
                    WHERE provider_configuration_id = :providerConfigurationId"
            );
            $deleteStatement->bindValue(':providerConfigurationId', $providerConfigurationId, \PDO::PARAM_INT);
            $deleteStatement->execute();
        }
    }

    /**
     * @inheritDoc
     */
    public function insertAuthorizationRules(array $authorizationRules): void
    {
        $query = sprintf("SELECT id FROM provider_configuration WHERE name='%s'", Provider::SAML);
        $statement = $this->db->query($query);
        if ($statement !== false && ($result = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            $providerConfigurationId = (int)$result['id'];
            $insertStatement = $this->db->prepare(
                "INSERT INTO security_provider_access_group_relation
                    (claim_value, access_group_id, provider_configuration_id, priority)
                    VALUES (:claimValue, :accessGroupId, :providerConfigurationId, :priority)"
            );

            foreach ($authorizationRules as $authorizationRule) {
                $insertStatement->bindValue(':claimValue', $authorizationRule->getClaimValue());
                $insertStatement->bindValue(
                    ':accessGroupId',
                    $authorizationRule->getAccessGroup()->getId(),
                    \PDO::PARAM_INT
                );
                $insertStatement->bindValue(
                    ':providerConfigurationId',
                    $providerConfigurationId,
                    \PDO::PARAM_INT
                );
                $insertStatement->bindValue(
                    ':priority',
                    $authorizationRule->getPriority(),
                    \PDO::PARAM_INT
                );
                $insertStatement->execute();
            }
        }
    }

    /**
     * Delete Contact Group relations
     */
    private function deleteContactGroupRelations(): void
    {
        $query = sprintf("SELECT id FROM provider_configuration WHERE name='%s'", Provider::SAML);
        $statement = $this->db->query($query);
        if ($statement !== false && ($result = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            $providerConfigurationId = (int) $result['id'];
            $deleteStatement = $this->db->prepare(
                "DELETE FROM security_provider_contact_group_relation
                    WHERE provider_configuration_id = :providerConfigurationId"
            );
            $deleteStatement->bindValue(':providerConfigurationId', $providerConfigurationId, \PDO::PARAM_INT);
            $deleteStatement->execute();
        }
    }

    /**
     * Insert Contact Group Relations
     *
     * @param ContactGroupRelation[] $contactGroupRelations
     */
    private function insertContactGroupRelations(array $contactGroupRelations): void
    {
        $query = sprintf("SELECT id FROM provider_configuration WHERE name='%s'", Provider::SAML);
        $statement = $this->db->query($query);
        if ($statement !== false && ($result = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
            $providerConfigurationId = (int)$result['id'];
            $insertStatement = $this->db->prepare(
                "INSERT INTO security_provider_contact_group_relation
                    (claim_value, contact_group_id, provider_configuration_id)
                        VALUES (:claimValue, :contactGroupId, :providerConfigurationId)"
            );
            foreach ($contactGroupRelations as $contactGroupRelation) {
                $insertStatement->bindValue(':claimValue', $contactGroupRelation->getClaimValue());
                $insertStatement->bindValue(
                    ':contactGroupId',
                    $contactGroupRelation->getContactGroup()->getId(),
                    \PDO::PARAM_INT
                );
                $insertStatement->bindValue(
                    ':providerConfigurationId',
                    $providerConfigurationId,
                    \PDO::PARAM_INT
                );
                $insertStatement->execute();
            }
        }
    }

    /**
     * @param AuthenticationConditions $authenticationConditions
     * @return array<string,array<string|null>|bool|string>
     */
    private function authenticationConditionsToArray(AuthenticationConditions $authenticationConditions): array
    {
        $conditionsSettings = [
            "is_enabled" => $authenticationConditions->isEnabled(),
            "attribute_path" => $authenticationConditions->getAttributePath(),
            "authorized_values" => $authenticationConditions->getAuthorizedValues(),
            "trusted_client_addresses" => $authenticationConditions->getTrustedClientAddresses(),
            "blacklist_client_addresses" => $authenticationConditions->getBlacklistClientAddresses(),
        ];

        $endpoint = $authenticationConditions->getEndpoint();
        if ($endpoint) {
            $conditionsSettings['endpoint'] = $endpoint->toArray();
        }

        return $conditionsSettings;
    }

    /**
     * @param GroupsMapping $groupsMapping
     * @return array<string,bool|string|array<string,string|null>>
     */
    private function groupsMappingToArray(GroupsMapping $groupsMapping): array
    {
        $groupsSettings = [
            "is_enabled" => $groupsMapping->isEnabled(),
            "attribute_path" => $groupsMapping->getAttributePath(),
        ];

        $endpoint = $groupsMapping->getEndpoint();
        if ($endpoint) {
            $groupsSettings['endpoint'] = $endpoint->toArray();
        }

        return $groupsSettings;
    }

    /**
     * @param ACLConditions $aclConditions
     * @return array<string,bool|string|array<string,string|null>>
     */
    private function aclConditionsToArray(ACLConditions $aclConditions): array
    {
        $rolesSettings = [
            'is_enabled' => $aclConditions->isEnabled(),
            'apply_only_first_role' => $aclConditions->onlyFirstRoleIsApplied(),
            'attribute_path' => $aclConditions->getAttributePath()
        ];

        $endpoint = $aclConditions->getEndpoint();
        if ($endpoint) {
            $rolesSettings['endpoint'] = $endpoint->toArray();
        }

        return $rolesSettings;
    }
}
