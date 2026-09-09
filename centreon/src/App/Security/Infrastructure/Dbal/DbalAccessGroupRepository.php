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

use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\AccessGroupRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DbalAccessGroupRepository implements AccessGroupRepository
{
    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
    ) {
    }

    public function userHasGroup(UserId $userId, string $groupName): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('1')
            ->from('acl_groups', 'ag')
            ->where("ag.acl_group_activate = '1'")
            ->andWhere('ag.acl_group_name = :groupName')
            ->andWhere(
                $qb->expr()->or(
                    'ag.acl_group_id IN (
                        SELECT acl_group_id
                        FROM acl_group_contacts_relations
                        WHERE contact_contact_id = :contactId
                    )',
                    'ag.acl_group_id IN (
                        SELECT agcr.acl_group_id
                        FROM acl_group_contactgroups_relations agcr
                        INNER JOIN contactgroup_contact_relation cgcr
                            ON cgcr.contactgroup_cg_id = agcr.cg_cg_id
                        WHERE cgcr.contact_contact_id = :contactId
                    )'
                )
            )
            ->setMaxResults(1)
            ->setParameter('groupName', $groupName)
            ->setParameter('contactId', $userId->value);

        return (bool) $qb->executeQuery()->fetchOne();
    }
}
