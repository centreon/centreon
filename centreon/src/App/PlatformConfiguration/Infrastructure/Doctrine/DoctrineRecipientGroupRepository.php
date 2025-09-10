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
use App\PlatformConfiguration\Domain\Aggregate\RecipientGroupId;
use App\PlatformConfiguration\Domain\Aggregate\RecipientGroupName;
use App\PlatformConfiguration\Domain\Aggregate\RecipientId;
use App\PlatformConfiguration\Domain\Repository\RecipientGroupRepository;
use App\Shared\Domain\Aggregate\Collection;
use App\Shared\Infrastructure\Doctrine\DoctrineRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @phpstan-type RowTypeAlias = array{contact_id: int, contact_name: string}
 */
final readonly class DoctrineRecipientGroupRepository extends DoctrineRepository implements RecipientGroupRepository
{
    private const TABLE_NAME = 'contact';

    public function __construct(
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
    ) {
    }

    public function add(RecipientGroup $group): void
    {
    }

    public function findByRecipient(Recipient $recipient): Collection
    {
        // TODO check id, if the same, keep reference
    }

    /**
     * @param RowTypeAlias $row
     */
    private function createGroup(array $row): RecipientGroup
    {
        // create a group without recipient, that will
        // be used to search related recipients
        $group = new RecipientGroup(
            id: new RecipientGroupId($row['contact_id']),
            name: new RecipientGroupName($row['contact_nme']),
            groups: new Collection([], Recipient::class),
        );

        return new RecipientGroup(
            id: $group->id(),
            name: $group->name,
            groups: $this->groupRepository->findByRecipient($group),
        );
    }
}
