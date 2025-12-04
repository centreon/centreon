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

namespace App\Security\Infrastructure\Dbal;

use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Exception\CredentialDoesNotExistException;
use App\Security\Domain\Repository\CredentialRepository;
use App\Shared\Infrastructure\Dbal\DbalRepository;
use App\Shared\Infrastructure\TransformerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * @phpstan-type RowTypeAlias = array{
 *   c_id: int,
 *   c_alias: non-empty-string,
 *   c_admin: string,
 *   c_active: string,
 *   topology_permissions: array<string>,
 *   action_rules: array<string>
 * }
 */
final readonly class DbalCredentialRepository extends DbalRepository implements CredentialRepository
{
    public const TABLE_NAME = 'contact';
    private const MENU_ACCESS_NO_ACCESS = 0;
    private const MENU_ACCESS_READ_WRITE = 1;
    private const MENU_ACCESS_READ_ONLY = 2;

    /**
     * @param TransformerInterface<RowTypeAlias, Credential> $transformer
     */
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,

        #[Autowire(service: DbalCredentialTransformer::class)]
        private TransformerInterface $transformer,
    ) {
    }

    public function getByToken(string $token): Credential
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('c.contact_id AS c_id', 'c.contact_alias AS c_alias', 'c.contact_admin AS c_admin', 'c.contact_activate AS c_active')
            ->from(self::TABLE_NAME, 'c')
            ->join('c', 'security_authentication_tokens', 'sat', 'c.contact_id = sat.user_id')
            ->join('sat', 'security_token', 'st', 'sat.provider_token_id = st.id')
            ->where('sat.token = :token OR st.token = :token')
            ->andWhere('sat.is_revoked = 0')
            ->setParameter('token', $token)
            ->setMaxResults(1);

        /** @var array{c_id: int, c_alias: string, c_admin: string, c_active: string}|false $row */
        $row = $qb->executeQuery()->fetchAssociative();
        if ($row === false) {
            throw new CredentialDoesNotExistException(['token' => $token]);
        }

        return $this->createCredential($row);
    }

    public function getBySession(string $sessionId): Credential
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('c.contact_id AS c_id', 'c.contact_alias AS c_alias', 'c.contact_admin AS c_admin', 'c.contact_activate AS c_active')
            ->from(self::TABLE_NAME, 'c')
            ->join('c', 'session', 's', 'c.contact_id = s.user_id')
            ->where('s.session_id = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->setMaxResults(1);

        /** @var array{c_id: int, c_alias: string, c_admin: string, c_active: string}|false $row */
        $row = $qb->executeQuery()->fetchAssociative();
        if ($row === false) {
            throw new CredentialDoesNotExistException(['sessionId' => $sessionId]);
        }

        return $this->createCredential($row);
    }

    /**
     * @param array{c_id: int, c_alias: string, c_admin: string, c_active: string} $row
     */
    private function createCredential(array $row): Credential
    {
        Assert::notEmpty($row['c_alias']);

        $topologies = $this->fetchTopologyRules($row['c_id'], $row['c_admin'] === '1');
        $topologyPermissions = $this->buildTopologyPermissions($topologies);
        $actionRules = $this->fetchActionRules($row['c_id']);

        /** @var RowTypeAlias $rowWithPermissions */
        $rowWithPermissions = [
            ...$row,
            'topology_permissions' => $topologyPermissions,
            'action_rules' => $actionRules,
        ];

        return $this->transformer->transform($rowWithPermissions);
    }

    /**
     * @return array<int, array{name: string, page: int, parent: int|null, access_right: int|null}>
     */
    private function fetchTopologyRules(int $contactId, bool $isAdmin): array
    {
        if ($isAdmin) {
            return $this->fetchTopologyRulesForAdmin();
        }

        return $this->fetchTopologyRulesForNonAdmin($contactId);
    }

    /**
     * @return array<int, array{name: string, page: int, parent: int|null, access_right: int|null}>
     */
    private function fetchTopologyRulesForAdmin(): array
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('t.topology_name', 't.topology_page', 't.topology_parent')
            ->from('topology', 't')
            ->where($qb->expr()->isNotNull('t.topology_page'))
            ->orderBy('t.topology_page');

        $result = $qb->executeQuery();
        $topologies = [];

        /** @var array<array{topology_page: int, topology_name: string, topology_parent: ?string}> $rows */
        $rows = $result->fetchAllAssociative();

        foreach ($rows as $row) {
            $topologyPage = (int) $row['topology_page'];
            $topologies[$topologyPage] = [
                'name' => $row['topology_name'],
                'page' => $topologyPage,
                'parent' => $row['topology_parent'] !== null ? (int) $row['topology_parent'] : null,
                'access_right' => 1,
            ];
        }

        return $topologies;
    }

    /**
     * @return array<int, array{name: string, page: int, parent: int|null, access_right: int|null}>
     */
    private function fetchTopologyRulesForNonAdmin(int $contactId): array
    {
        // subquery for topology access rights
        $subQb = $this->connection->createQueryBuilder();
        $subQb->select('topology.topology_id', 'acltr.access_right')
            ->from('contact', 'contact')
            ->leftJoin('contact', 'contactgroup_contact_relation', 'cgcr', 'cgcr.contact_contact_id = contact.contact_id')
            ->leftJoin('cgcr', 'acl_group_contactgroups_relations', 'gcgr', 'gcgr.cg_cg_id = cgcr.contactgroup_cg_id')
            ->leftJoin('contact', 'acl_group_contacts_relations', 'gcr', 'gcr.contact_contact_id = contact.contact_id')
            ->leftJoin(
                'gcr',
                'acl_group_topology_relations',
                'agtr',
                'agtr.acl_group_id = gcr.acl_group_id OR agtr.acl_group_id = gcgr.acl_group_id'
            )
            ->leftJoin('agtr', 'acl_topology_relations', 'acltr', 'acltr.acl_topo_id = agtr.acl_topology_id')
            ->innerJoin('acltr', 'topology', 'topology', 'topology.topology_id = acltr.topology_topology_id')
            ->where('contact.contact_id = :contact_id')
            ->setParameter('contact_id', $contactId);

        $qb = $this->connection->createQueryBuilder();
        $qb->select('t.topology_name', 't.topology_page', 't.topology_parent', 'access.access_right')
            ->from('topology', 't')
            ->leftJoin(
                't',
                sprintf('(%s)', $subQb->getSQL()),
                'access',
                't.topology_id = access.topology_id'
            )
            ->where($qb->expr()->isNotNull('t.topology_page'))
            ->orderBy('t.topology_page')
            ->setParameter('contact_id', $contactId);

        $result = $qb->executeQuery();
        $topologies = [];

        /** @var array<array{topology_page: int, topology_name: string, topology_parent: ?string, access_right: ?string}> $rows */
        $rows = $result->fetchAllAssociative();

        foreach ($rows as $row) {
            $topologyPage = (int) $row['topology_page'];
            $topologies[$topologyPage] = [
                'name' => $row['topology_name'],
                'page' => $topologyPage,
                'parent' => $row['topology_parent'] !== null ? (int) $row['topology_parent'] : null,
                'access_right' => $row['access_right'] !== null ? (int) $row['access_right'] : null,
            ];
        }

        return $topologies;
    }

    /**
     * Fetch action rules for a contact.
     * Inspired by addActionRules from ContactRepositoryRDB.
     *
     * @return array<string>
     */
    private function fetchActionRules(int $contactId): array
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('DISTINCT rules.acl_action_name')
            ->from('contact', 'contact')
            ->leftJoin('contact', 'contactgroup_contact_relation', 'cgcr', 'cgcr.contact_contact_id = contact.contact_id')
            ->leftJoin('cgcr', 'acl_group_contactgroups_relations', 'gcgr', 'gcgr.cg_cg_id = cgcr.contactgroup_cg_id')
            ->leftJoin('contact', 'acl_group_contacts_relations', 'gcr', 'gcr.contact_contact_id = contact.contact_id')
            ->leftJoin(
                'gcr',
                'acl_group_actions_relations',
                'agar',
                'agar.acl_group_id = gcr.acl_group_id OR agar.acl_group_id = gcgr.acl_group_id'
            )
            ->leftJoin('agar', 'acl_actions', 'actions', 'actions.acl_action_id = agar.acl_action_id')
            ->leftJoin('actions', 'acl_actions_rules', 'rules', 'rules.acl_action_rule_id = actions.acl_action_id')
            ->where('contact.contact_id = :contact_id')
            ->andWhere($qb->expr()->isNotNull('rules.acl_action_name'))
            ->orderBy('rules.acl_action_name')
            ->setParameter('contact_id', $contactId);

        $result = $qb->executeQuery();
        $actionRules = [];

        /** @var array<array{acl_action_name: string}> $rows */
        $rows = $result->fetchAllAssociative();

        foreach ($rows as $row) {
            $actionRules[] = $row['acl_action_name'];
        }

        return $actionRules;
    }

    /**
     * Build topology permissions from topology data.
     * Inspired by addTopologyRules from ContactRepositoryRDB.
     *
     * @param array<int, array{name: string, page: int, parent: int|null, access_right: int|null}> $topologies
     *
     * @return array<string>
     */
    private function buildTopologyPermissions(array $topologies): array
    {
        $processedTopologies = [];
        $rightsCounter = 0;

        foreach ($topologies as $topologyPage => $topology) {
            $topologyAccess = $topology['access_right'];
            // skip topologies with no access
            if ($topologyAccess === null) {
                continue;
            }
            if ($topologyAccess <= self::MENU_ACCESS_NO_ACCESS) {
                continue;
            }

            // if topology_page already registered, only update the rights when needed (giving more access)
            if (isset($processedTopologies[$topologyPage])) {
                if (
                    $topologyAccess === self::MENU_ACCESS_READ_WRITE
                    && $processedTopologies[$topologyPage]['right'] !== self::MENU_ACCESS_READ_WRITE
                ) {
                    $processedTopologies[$topologyPage]['right'] = self::MENU_ACCESS_READ_WRITE;
                }
            } else {
                $processedTopologies[$topologyPage] = [
                    'name' => $topology['name'],
                    'right' => $topologyAccess,
                ];
                $rightsCounter++;
            }
        }

        // build permission names from processed topologies
        $permissions = [];
        if ($rightsCounter > 0) {
            $nameOfTopologiesRules = [];

            foreach ($processedTopologies as $topologyPage => $details) {
                $originalTopologyPage = $topologyPage;
                $ruleName = null;
                $lvl2Name = null;
                $lvl3Name = null;
                $lvl4Name = null;

                if (mb_strlen((string) $topologyPage) === 7) {
                    $lvl4Name = $processedTopologies[$topologyPage]['name'];
                    $topologyPage = (int) mb_substr((string) $topologyPage, 0, 5);

                    // avoid creating entry for the parent menu
                    $nameOfTopologiesRules[$topologyPage] = null;
                }
                if (mb_strlen((string) $topologyPage) === 5) {
                    if ($lvl4Name === null && array_key_exists($topologyPage, $nameOfTopologiesRules)) {
                        continue;
                    }
                    $lvl3Name = $processedTopologies[$topologyPage]['name'];
                    $topologyPage = (int) mb_substr((string) $topologyPage, 0, 3);
                }
                if (mb_strlen((string) $topologyPage) === 3) {
                    $lvl2Name = $processedTopologies[$topologyPage]['name'];
                    $topologyPage = (int) mb_substr((string) $topologyPage, 0, 1);
                }
                if (mb_strlen((string) $topologyPage) === 1) {
                    $ruleName = 'ROLE_' . $processedTopologies[$topologyPage]['name'];
                }
                if ($lvl2Name !== null) {
                    $ruleName .= '_' . $lvl2Name;
                }
                if ($lvl3Name !== null) {
                    $ruleName .= '_' . $lvl3Name;
                }
                if ($lvl4Name !== null) {
                    $ruleName .= '_' . $lvl4Name;
                }

                $ruleName .= ($details['right'] === self::MENU_ACCESS_READ_ONLY) ? '_R' : '_RW';

                $nameOfTopologiesRules[$originalTopologyPage] = $ruleName;
            }

            foreach ($nameOfTopologiesRules as $name) {
                if ($name !== null) {
                    $name = preg_replace(['/\s/', '/\W/'], ['_', ''], $name);
                    $name = mb_strtoupper((string) $name);
                    $permissions[] = $name;
                }
            }
        }

        return $permissions;
    }
}
