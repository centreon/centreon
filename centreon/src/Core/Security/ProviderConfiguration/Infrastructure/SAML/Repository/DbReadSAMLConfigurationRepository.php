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

namespace Core\Security\ProviderConfiguration\Infrastructure\SAML\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Contact\Domain\Model\ContactTemplate;
use Core\Contact\Infrastructure\Repository\DbContactGroupFactory;
use Core\Contact\Infrastructure\Repository\DbContactTemplateFactory;
use Core\Security\AccessGroup\Infrastructure\Repository\DbAccessGroupFactory;
use Core\Security\ProviderConfiguration\Application\SAML\Repository\ReadSAMLConfigurationRepositoryInterface
    as ReadRepositoryInterface;
use Core\Security\ProviderConfiguration\Domain\Model\AuthorizationRule;
use Core\Security\ProviderConfiguration\Domain\Model\ContactGroupRelation;
use Throwable;

/**
 * @phpstan-import-type _AccessGroupRecord from DbAccessGroupFactory
 */
class DbReadSAMLConfigurationRepository extends AbstractRepositoryDRB implements ReadRepositoryInterface
{
    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * Get Contact Template.
     *
     * @param int $contactTemplateId
     *
     * @throws Throwable
     *
     * @return ContactTemplate|null
     */
    public function getContactTemplate(int $contactTemplateId): ?ContactTemplate
    {
        $statement = $this->db->prepare(
            'SELECT
                contact_id,
                contact_name
            FROM contact
            WHERE
                contact_id = :contactTemplateId
                AND contact_register = 0'
        );
        $statement->bindValue(':contactTemplateId', $contactTemplateId, \PDO::PARAM_INT);
        $statement->execute();

        $contactTemplate = null;
        if ($statement !== false && $result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $contactTemplate = DbContactTemplateFactory::createFromRecord($result);
        }

        return $contactTemplate;
    }

    /**
     * Get Contact Group.
     *
     * @param int $contactGroupId
     *
     * @throws Throwable
     *
     * @return ContactGroup|null
     */
    public function getContactGroup(int $contactGroupId): ?ContactGroup
    {
        $statement = $this->db->prepare(
            $this->translateDbName(<<<'SQL'
                SELECT cg_id, cg_name, cg_alias, cg_comment, cg_activate, cg_type
                FROM `:db`.contactgroup
                WHERE cg_id = :contactGroupId
                SQL
            )
        );
        $statement->bindValue(':contactGroupId', $contactGroupId, \PDO::PARAM_INT);
        $statement->execute();

        $contactGroup = null;
        if ($statement !== false && $result = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $contactGroup = DbContactGroupFactory::createFromRecord($result);
        }

        return $contactGroup;
    }

    /**
     * Get Authorization Rules.
     *
     * @param int $providerConfigurationId
     *
     * @throws Throwable
     *
     * @return array<AuthorizationRule>
     */
    public function getAuthorizationRulesByConfigurationId(int $providerConfigurationId): array
    {
        $statement = $this->db->prepare(
            'SELECT * from security_provider_access_group_relation spagn
                INNER JOIN acl_groups ON acl_group_id = spagn.access_group_id
                WHERE spagn.provider_configuration_id = :providerConfigurationId
                ORDER BY spagn.priority asc'
        );
        $statement->bindValue(':providerConfigurationId', $providerConfigurationId, \PDO::PARAM_INT);
        $statement->execute();

        $authorizationRules = [];
        while ($statement !== false && is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            /** @var _AccessGroupRecord $result */
            $accessGroup = DbAccessGroupFactory::createFromRecord($result);
            $authorizationRules[] = new AuthorizationRule($result['claim_value'], $accessGroup, $result['priority']);
        }

        return $authorizationRules;
    }

    /**
     * Get Contact Group relations.
     *
     * @param int $providerConfigurationId
     *
     * @throws Throwable
     *
     * @return ContactGroupRelation[]
     */
    public function getContactGroupRelationsByConfigurationId(int $providerConfigurationId): array
    {
        $statement = $this->db->prepare(
            $this->translateDbName(<<<'SQL'
                SELECT *
                FROM `:db`.security_provider_contact_group_relation spcgn
                INNER JOIN `:db`.contactgroup
                    ON cg_id = spcgn.contact_group_id
                WHERE spcgn.provider_configuration_id = :providerConfigurationId
                SQL
            )
        );
        $statement->bindValue(':providerConfigurationId', $providerConfigurationId, \PDO::PARAM_INT);
        $statement->execute();

        $contactGroupRelations = [];
        while ($statement !== false && is_array($result = $statement->fetch(\PDO::FETCH_ASSOC))) {
            /** @var array{
             *     cg_id: int,
             *     cg_name: string,
             *     cg_alias: string,
             *     cg_comment?: string,
             *     cg_activate: string,
             *     cg_type: string,
             *     claim_value: string
             * } $result
             */
            $contactGroup = DbContactGroupFactory::createFromRecord($result);
            $contactGroupRelations[] = new ContactGroupRelation($result['claim_value'], $contactGroup);
        }

        return $contactGroupRelations;
    }
}
