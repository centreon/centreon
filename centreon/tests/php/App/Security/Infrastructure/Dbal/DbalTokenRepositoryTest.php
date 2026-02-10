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

use App\Security\Domain\Exception\TokenDoesNotExistException;
use App\Security\Infrastructure\Dbal\DbalTokenRepository;
use Tests\App\Shared\ApiTestCase;

class DbalTokenRepositoryTest extends ApiTestCase
{
    private DbalTokenRepository $repository;

    protected function setUp(): void
    {
        /** @var DbalTokenRepository $repository */
        $repository = self::getContainer()->get(DbalTokenRepository::class);
        $this->repository = $repository;
    }

    public function testGetThrowsExceptionIfTokenNotFound(): void
    {
        $this->expectException(TokenDoesNotExistException::class);
        $this->repository->get('unknown-token');
    }

    public function testGetReturnsTokenIfFound(): void
    {
        $this->login();
        $token = $this->repository->get((string) $this->token);
        $this->assertSame($this->token, $token->token);
    }
}
