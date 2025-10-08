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

namespace Core\Security\ProviderConfiguration\Infrastructure\SAML\Repository;

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Contact\Domain\Model\ContactTemplate;
use Core\Contact\Infrastructure\Repository\DbContactGroupFactory;
use Core\Contact\Infrastructure\Repository\DbContactTemplateFactory;
use Core\Security\AccessGroup\Infrastructure\Repository\DbAccessGroupFactory;
use Core\Security\ProviderConfiguration\Application\SAML\Repository\ReadSAMLConfigurationRepositoryInterface as ReadRepositoryInterface;
use Core\Security\ProviderConfiguration\Domain\Model\AuthorizationRule;
use Core\Security\ProviderConfiguration\Domain\Model\ContactGroupRelation;

/**
 * @phpstan-import-type _AccessGroupRecord from DbAccessGroupFactory
 */
class DbReadSAMLConfigurationRepository extends DatabaseRepository implements ReadRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function findOneContactTemplate(int $contactTemplateId): ?ContactTemplate
    {
        try {
            $query = $this->queryBuilder->select('contact_id', 'contact_name')
                ->from('`:db`.contact')
                ->where('contact_id = :contactTemplateId')
                ->andWhere('contact_register = :contact_register')
                ->getQuery();

            $queryParameters = QueryParameters::create([
                QueryParameter::int('contact_template_id', $contactTemplateId),
                QueryParameter::int('contact_register', 0),
            ]);

            $entry = $this->connection->fetchAssociative($this->translateDbName($query), $queryParameters);

            return $entry !== false ? DbContactTemplateFactory::createFromRecord($entry) : null;
        } catch (ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                message: 'Could not fetch contact template from database',
                context: ['contact_template_id' => $contactTemplateId],
                previous: $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function findOneContactGroup(int $contactGroupId): ?ContactGroup
    {
        try {
            $query = $this->queryBuilder->select(
                'cg_id',
                'cg_name',
                'cg_alias',
                'cg_comment',
                'cg_activate',
                'cg_type'
            )
                ->from('`:db`.contactgroup')
                ->where('cg_id = :contactGroupId')
                ->getQuery();

            $queryParameters = QueryParameters::create([
                QueryParameter::int('contactGroupId', $contactGroupId),
            ]);

            $entry = $this->connection->fetchAssociative($this->translateDbName($query), $queryParameters);

            return $entry !== false ? DbContactGroupFactory::createFromRecord($entry) : null;
        } catch (ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                message: 'Could not fetch contact group from database',
                context: ['contact_group_id' => $contactGroupId],
                previous: $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function findAuthorizationRulesByConfigurationId(int $providerConfigurationId): array
    {
        try {
            $query = <<<'SQL'
                SELECT * from `:db`.security_provider_access_group_relation spagn
                INNER JOIN `:db`.acl_groups
                    ON acl_group_id = spagn.access_group_id
                WHERE spagn.provider_configuration_id = :providerConfigurationId
                ORDER BY spagn.priority asc
                SQL;

            $queryParameters = QueryParameters::create([
                QueryParameter::int('providerConfigurationId', $providerConfigurationId),
            ]);

            $entries = $this->connection->fetchAllAssociative($query, $queryParameters);

            $authorizationRules = [];
            foreach ($entries as $entry) {
                /** @var _AccessGroupRecord $entry */
                $accessGroup = DbAccessGroupFactory::createFromRecord($entry);
                $authorizationRules[] = new AuthorizationRule($entry['claim_value'], $accessGroup, $entry['priority']);
            }

            return $authorizationRules;
        } catch (ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                message: 'Could not fetch authorization rules from database',
                context: ['provider_configuration_id' => $providerConfigurationId],
                previous: $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function findContactGroupRelationsByConfigurationId(int $providerConfigurationId): array
    {
        try {
            $query = <<<'SQL'
                SELECT *
                FROM `:db`.security_provider_contact_group_relation spcgn
                INNER JOIN `:db`.contactgroup
                    ON cg_id = spcgn.contact_group_id
                WHERE spcgn.provider_configuration_id = :providerConfigurationId
                SQL;

            $queryParameters = QueryParameters::create([
                QueryParameter::int('providerConfigurationId', $providerConfigurationId),
            ]);

            $entries = $this->connection->fetchAllAssociative($this->translateDbName($query), $queryParameters);

            $contactGroupRelations = [];
            foreach ($entries as $entry) {
                /** @var array{
                 *     cg_id: int,
                 *     cg_name: string,
                 *     cg_alias: string,
                 *     cg_comment?: string,
                 *     cg_activate: string,
                 *     cg_type: string,
                 *     claim_value: string
                 * } $entry
                 */
                $contactGroup = DbContactGroupFactory::createFromRecord($entry);
                $contactGroupRelations[] = new ContactGroupRelation($entry['claim_value'], $contactGroup);
            }

            return $contactGroupRelations;
        } catch (ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                message: 'Could not fetch contact group relations from database',
                context: ['provider_configuration_id' => $providerConfigurationId],
                previous: $e
            );
        }
    }
}
