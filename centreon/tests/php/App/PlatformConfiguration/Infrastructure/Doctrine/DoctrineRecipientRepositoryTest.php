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

namespace Tests\App\PlatformConfiguration\Infrastructure\Doctrine;

use App\PlatformConfiguration\Domain\Aggregate\Recipient;
use App\PlatformConfiguration\Domain\Aggregate\RecipientGroup;
use App\PlatformConfiguration\Domain\Aggregate\RecipientGroupName;
use App\PlatformConfiguration\Domain\Aggregate\RecipientName;
use App\PlatformConfiguration\Infrastructure\Doctrine\DoctrineRecipientRepository;
use App\Shared\Domain\Aggregate\Collection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineRecipientRepositoryTest extends KernelTestCase
{
    private DoctrineRecipientRepository $repository;

    protected function setUp(): void
    {
        /** @var DoctrineActivityLogRepository $repository */
        $repository = self::getContainer()->get(DoctrineRecipientRepository::class);

        $this->repository = $repository;
    }

    /** @group wip */
    public function testFind(): void
    {
        $recipient = new Recipient(
            id: null,
            name: new RecipientName('recipient'),
            groups: new Collection([], RecipientGroup::class),
        );

        $group = new RecipientGroup(
            id: null,
            name: new RecipientGroupName('group'),
            recipients: new Collection([], Recipient::class),
        );

        $recipient->addGroup($group);

        $this->repository->add($recipient);

        dd($this->repository->find($recipient->id()));
    }
}
