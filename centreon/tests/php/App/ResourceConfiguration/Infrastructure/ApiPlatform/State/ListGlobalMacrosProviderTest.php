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

namespace Tests\App\ResourceConfiguration\Infrastructure\ApiPlatform\State;

use App\ResourceConfiguration\Infrastructure\ApiPlatform\Resource\GlobalMacroResource;
use Symfony\Component\HttpFoundation\Response;
use Tests\App\Shared\ApiTestCase;

final class ListGlobalMacrosProviderTest extends ApiTestCase
{
    /** @group wip */
    public function testFindAllGlobalMacrosWithoutParameter(): void
    {
        $this->login();

        $this->request('GET', '/api/latest/configuration/macros/globals');
        self::assertResponseIsSuccessful();
        self::assertMatchesResourceCollectionJsonSchema(GlobalMacroResource::class);
        self::assertJsonContains(
            [
                'member' => [
                    ['name' => '$USER1$'],
                ],
            ]
        );
    }

    /** @group wip */
    public function testFindAllGlobalMacrosIsUnauthorizedForUserWithoutSufficientACL(): void
    {
        $this->login('user');

        $this->request('GET', '/api/latest/configuration/macros/globals');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /** @group wip */
    public function testFindAllGlobalMacrosWithNameCriteria(): void
    {
        $this->login();
        $response = $this->request('GET', '/api/latest/configuration/macros/globals', ['query' => ['name' => 'USER1']]);
        self::assertResponseIsSuccessful();
        $this->assertCount(1, $response->toArray()['member']);
        self::assertJsonContains(
            [
                'member' => [
                    ['name' => '$USER1$'],
                ],
            ]
        );
    }

    protected static function apiUsers(): array
    {
        return ['user'];
    }
}
