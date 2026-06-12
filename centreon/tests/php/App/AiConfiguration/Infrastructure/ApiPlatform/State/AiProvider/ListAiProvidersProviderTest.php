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

namespace Tests\App\AiConfiguration\Infrastructure\ApiPlatform\State\AiProvider;

final class ListAiProvidersProviderTest extends AiProviderApiTestCase
{
    public function testUnauthenticatedReturns401(): void
    {
        $this->request('GET', self::ENDPOINT);

        self::assertResponseStatusCodeSame(401);
    }

    public function testUserWithoutPermissionReturns403(): void
    {
        $this->login(self::USER_WITHOUT_RIGHTS);

        $this->request('GET', self::ENDPOINT);

        self::assertResponseStatusCodeSame(403);
    }

    public function testListAiProviders(): void
    {
        $this->login();

        $this->createProvider('Anthropic production');
        $this->createProvider('Mistral fallback', type: 'mistral');

        $response = $this->request('GET', self::ENDPOINT);

        self::assertResponseIsSuccessful();
        $content = $response->toArray();
        /** @var list<array<string, mixed>> $members */
        $members = $content['hydra:member'] ?? $content;
        self::assertCount(2, $members);
        foreach ($members as $member) {
            self::assertArrayNotHasKey('api_token', $member);
        }
    }

    public function testFilterByName(): void
    {
        $this->login();

        $this->createProvider('Anthropic production');
        $this->createProvider('Mistral fallback', type: 'mistral');

        $response = $this->request('GET', self::ENDPOINT . '?name[lk]=mistral');

        self::assertResponseIsSuccessful();
        $content = $response->toArray();
        /** @var list<array<string, mixed>> $members */
        $members = $content['hydra:member'] ?? $content;
        self::assertCount(1, $members);
        self::assertSame('Mistral fallback', $members[0]['name']);
    }

    public function testGetItemDoesNotExposeToken(): void
    {
        $this->login();

        $id = $this->createProvider('Anthropic production');

        $response = $this->request('GET', self::ENDPOINT . '/' . $id);

        self::assertResponseIsSuccessful();
        self::assertArrayNotHasKey('api_token', $response->toArray());
    }
}
