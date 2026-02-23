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

namespace Tests\App\Security\Infrastructure\Dbal;

use App\Security\Domain\Exception\CredentialDoesNotExistException;
use App\Security\Infrastructure\Dbal\DbalCredentialRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalCredentialRepositoryTest extends KernelTestCase
{
    private DbalCredentialRepository $repository;

    protected function setUp(): void
    {
        /** @var DbalCredentialRepository $repository */
        $repository = self::getContainer()->get(DbalCredentialRepository::class);
        $this->repository = $repository;
    }

    public function testGetByUsernameReturnsCredential(): void
    {
        $username = 'admin';
        $credential = $this->repository->getByUsername($username);

        self::assertSame($username, $credential->identifier->value);
    }

    public function testGetByUsernameThrowsExceptionIfNotFound(): void
    {
        $this->expectException(CredentialDoesNotExistException::class);
        $this->repository->getByUsername('invalid-user');
    }
}
