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
use Adaptation\Database\QueryBuilder\Exception\QueryBuilderException;
use Assert\AssertionFailedException;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Infrastructure\Repository\DatabaseRepository;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Contact\Domain\Model\ContactTemplate;
use Core\Contact\Infrastructure\Repository\DbContactGroupFactory;
use Core\Contact\Infrastructure\Repository\DbContactTemplateFactory;
use Core\Security\AccessGroup\Infrastructure\Repository\DbAccessGroupFactory;
use Core\Security\ProviderConfiguration\Application\SAML\Repository\ReadSAMLConfigurationRepositoryInterface;
use Core\Security\ProviderConfiguration\Domain\Model\AuthorizationRule;
use Core\Security\ProviderConfiguration\Domain\Model\ContactGroupRelation;

/**
 * @phpstan-import-type _AccessGroupRecord from DbAccessGroupFactory
 */
class DbReadSAMLConfigurationRepository extends DatabaseRepository implements ReadSAMLConfigurationRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function findOneContactTemplate(int $contactTemplateId): ?ContactTemplate
    {
        try {
            $query = $this->connection->createQueryBuilder()
                ->select('contact_id', 'contact_name')
                ->from('`:db`.contact')
                ->where('contact_id = :contactTemplateId')
                ->andWhere('contact_register = :contactRegister')
                ->getQuery();

            $queryParameters = QueryParameters::create([
                QueryParameter::int('contactTemplateId', $contactTemplateId),
                QueryParameter::int('contactRegister', 0),
            ]);

            $entry = $this->connection->fetchAssociative($this->translateDbName($query), $queryParameters);
        } catch (QueryBuilderException|ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                message: 'Could not fetch contact template from database for SAML configuration',
                context: ['contact_template_id' => $contactTemplateId],
                previous: $e
            );
        }

        return $entry !== false ? DbContactTemplateFactory::createFromRecord($entry) : null;
    }

    /**
     * @inheritDoc
     */
    public function findOneContactGroup(int $contactGroupId): ?ContactGroup
    {
        try {
            $query = $this->connection->createQueryBuilder()
                ->select('cg_id', 'cg_name', 'cg_alias', 'cg_comment', 'cg_activate', 'cg_type')
                ->from('`:db`.contactgroup')
                ->where('cg_id = :contactGroupId')
                ->getQuery();

            $queryParameters = QueryParameters::create([
                QueryParameter::int('contactGroupId', $contactGroupId),
            ]);

            $entry = $this->connection->fetchAssociative($this->translateDbName($query), $queryParameters);
        } catch (QueryBuilderException|ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                message: 'Could not fetch contact group from database for SAML configuration',
                context: ['contact_group_id' => $contactGroupId],
                previous: $e
            );
        }

        try {
            /** @var array{
             *     cg_id: int,
             *     cg_name: string,
             *     cg_alias: string,
             *     cg_comment?: string,
             *     cg_activate: string,
             *     cg_type: string,
             *     claim_value: string
             * }|false $entry
             */
            return $entry !== false ? DbContactGroupFactory::createFromRecord($entry) : null;
        } catch (AssertionFailedException $e) {
            throw new RepositoryException(
                message: 'Contact group record is invalid for SAML configuration',
                context: ['contact_group_id' => $contactGroupId, 'record' => $entry],
                previous: $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function findAuthorizationRulesByConfigurationId(int $providerConfigurationId): array
    {
        $query = <<<'SQL'
            SELECT * from `:db`.security_provider_access_group_relation spagn
            INNER JOIN `:db`.acl_groups ag
                ON ag.acl_group_id = spagn.access_group_id
            WHERE spagn.provider_configuration_id = :providerConfigurationId
            ORDER BY spagn.priority asc
            SQL;

        try {
            $queryParameters = QueryParameters::create([
                QueryParameter::int('providerConfigurationId', $providerConfigurationId),
            ]);

            $entries = $this->connection->fetchAllAssociative($this->translateDbName($query), $queryParameters);
        } catch (ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                message: 'Could not fetch authorization rules from database for SAML configuration',
                context: ['provider_configuration_id' => $providerConfigurationId],
                previous: $e
            );
        }

        $authorizationRules = [];
        foreach ($entries as $entry) {
            try {
                /** @var _AccessGroupRecord $entry */
                $accessGroup = DbAccessGroupFactory::createFromRecord($entry);
                $authorizationRules[] = new AuthorizationRule($entry['claim_value'], $accessGroup, $entry['priority']);
            } catch (AssertionFailedException $e) {
                throw new RepositoryException(
                    message: 'Access group record is invalid for SAML configuration',
                    context: ['record' => $entry, 'provider_configuration_id' => $providerConfigurationId],
                    previous: $e
                );
            }
        }

        return $authorizationRules;
    }

    /**
     * @inheritDoc
     */
    public function findContactGroupRelationsByConfigurationId(int $providerConfigurationId): array
    {
        $query = <<<'SQL'
            SELECT *
            FROM `:db`.security_provider_contact_group_relation spcgn
            INNER JOIN `:db`.contactgroup
                ON cg_id = spcgn.contact_group_id
            WHERE spcgn.provider_configuration_id = :providerConfigurationId
            SQL;

        try {
            $queryParameters = QueryParameters::create([
                QueryParameter::int('providerConfigurationId', $providerConfigurationId),
            ]);

            $entries = $this->connection->fetchAllAssociative($this->translateDbName($query), $queryParameters);
        } catch (ValueObjectException|CollectionException|ConnectionException $e) {
            throw new RepositoryException(
                message: 'Could not fetch contact group relations from database for SAML configuration',
                context: ['provider_configuration_id' => $providerConfigurationId],
                previous: $e
            );
        }

        $contactGroupRelations = [];
        foreach ($entries as $entry) {
            try {
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
            } catch (AssertionFailedException $e) {
                throw new RepositoryException(
                    message: 'Contact group record is invalid for SAML configuration',
                    context: ['record' => $entry, 'provider_configuration_id' => $providerConfigurationId],
                    previous: $e
                );
            }
        }

        return $contactGroupRelations;
    }
}
