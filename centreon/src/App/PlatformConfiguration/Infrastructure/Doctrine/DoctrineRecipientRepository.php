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

namespace App\PlatformConfiguration\Infrastructure\Doctrine;

use App\PlatformConfiguration\Domain\Aggregate\Recipient;
use App\PlatformConfiguration\Domain\Aggregate\RecipientGroup;
use App\PlatformConfiguration\Domain\Aggregate\RecipientId;
use App\PlatformConfiguration\Domain\Aggregate\RecipientName;
use App\PlatformConfiguration\Domain\Repository\RecipientGroupRepository;
use App\PlatformConfiguration\Domain\Repository\RecipientRepository;
use App\Shared\Domain\Aggregate\Collection;
use App\Shared\Infrastructure\Doctrine\DoctrineRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{contact_id: int, contact_name: string, cg_id: int, cg_name: string}
 */
final readonly class DoctrineRecipientRepository extends DoctrineRepository implements RecipientRepository
{
    private const TABLE_NAME = 'contact';

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
        private RecipientGroupRepository $groupRepository,
    ) {
    }

    public function add(Recipient $recipient): void
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->insert(self::TABLE_NAME)
            ->values([
                'contact_name' => ':name',
                'contact_register' => '1',
            ])
            ->setParameter('name', $recipient->name->value)
            ->executeStatement();

        $id = (int) $this->connection->lastInsertId();

        if ($id === 0) {
            throw new \RuntimeException(sprintf('Unable to retrieve last insert ID for "%s".', self::TABLE_NAME));
        }

        $this->setId($recipient, new RecipientId($id));

        foreach ($recipient->groups as $group) {
            $this->groupRepository->add($group);
        }
    }

    public function find(RecipientId $id): ?Recipient
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('c.contact_id', 'c.contact_name', 'cg.*'/*, 'g.cg_id', 'g.cg_name' */)
            ->from(self::TABLE_NAME, 'c')
            ->where(
                'contact_id = :id',
                'contact_register = \'1\'',
            )
            ->leftJoin('c', 'contactgroup_contact_relation', 'cg', 'c.contact_id = cg.contact_contact_id')
            // ->innerJoin('cg', 'contactgroup', 'g', 'cg.contact_contact_id = g.cg_id')
            ->setParameter('id', $id->value);

        /**
         * @var array<RowTypeAlias> $rows
         */
        $rows = $qb->executeQuery()->fetchAllAssociative();
        dd($rows);

        if (! $row) {
            return null;
        }

        return $this->createRecipient($row);
    }

    public function findByGroup(RecipientGroup $group): Collection
    {
        // TODO check id, if the same, keep reference
    }

    /**
     * @param RowTypeAlias $row
     */
    public function createRecipient(array $row): Recipient
    {
        // create a recipient without groups, that will
        // be used to search related groups
        $recipient = new Recipient(
            id: new RecipientId($row['contact_id']),
            name: new RecipientName($row['contact_nme']),
            groups: new Collection([], RecipientGroup::class),
        );

        return new Recipient(
            id: $recipient->id(),
            name: $recipient->name,
            groups: $this->groupRepository->findByRecipient($recipient),
        );
    }
}
