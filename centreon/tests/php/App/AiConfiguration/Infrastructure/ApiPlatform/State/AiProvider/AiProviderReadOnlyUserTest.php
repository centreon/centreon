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

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\App\Shared\GrantsTopologyPages;

/**
 * A non-admin user holding the AI Providers page in read-only: it reads the
 * configurations, but can neither change them nor reach the providers.
 */
final class AiProviderReadOnlyUserTest extends AiProviderApiTestCase
{
    use GrantsTopologyPages;

    // Administration > Parameters > AI Providers: builds ROLE_ADMINISTRATION_PARAMETERS_AI_PROVIDERS_R[W]
    private const AI_PROVIDERS_TOPOLOGY_PAGES = [5, 501, 50116];
    private const ACL_ACCESS_READ_ONLY = 2;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;
    }

    public function testListsTheConfigurations(): void
    {
        $this->createProviderThenLoginAsReadOnlyUser();

        $response = $this->request('GET', self::ENDPOINT);

        self::assertResponseStatusCodeSame(200);
        $content = $response->toArray();
        /** @var list<array<string, mixed>> $members */
        $members = $content['hydra:member'] ?? $content;
        self::assertCount(1, $members);
        self::assertSame('Anthropic production', $members[0]['name']);
    }

    public function testReadsAConfiguration(): void
    {
        $id = $this->createProviderThenLoginAsReadOnlyUser();

        $this->request('GET', self::ENDPOINT . '/' . $id);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonContains(['name' => 'Anthropic production']);
    }

    public function testCanNotCreate(): void
    {
        $this->createProviderThenLoginAsReadOnlyUser();

        $this->request('POST', self::ENDPOINT, [
            'json' => [
                'name' => 'Mistral fallback',
                'type' => 'mistral',
                'api_token' => 'a-token',
                'default_model' => 'mistral-small-latest',
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCanNotUpdate(): void
    {
        $id = $this->createProviderThenLoginAsReadOnlyUser();

        $this->request('PATCH', self::ENDPOINT . '/' . $id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Renamed'],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCanNotDelete(): void
    {
        $id = $this->createProviderThenLoginAsReadOnlyUser();

        $this->request('DELETE', self::ENDPOINT . '/' . $id);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCanNotTestAStoredConfiguration(): void
    {
        $id = $this->createProviderThenLoginAsReadOnlyUser();

        $this->request('POST', self::ENDPOINT . '/' . $id . '/test', ['json' => []]);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function provideProviderCalls(): iterable
    {
        yield 'test connection' => [
            '/test-connection',
            ['type' => 'anthropic', 'default_model' => 'claude-sonnet-4-6', 'api_token' => 'a-token'],
        ];

        yield 'discover models' => ['/discover-models', ['type' => 'anthropic', 'api_token' => 'a-token']];

        yield 'model prices' => ['/model-prices', ['default_model' => 'gpt-4o']];
    }

    /**
     * @param array<string, string> $body
     */
    #[DataProvider('provideProviderCalls')]
    public function testCanNotCallTheProviders(string $path, array $body): void
    {
        $this->loginAsReadOnlyUser();

        $this->request('POST', self::ENDPOINT . $path, ['json' => $body]);

        self::assertResponseStatusCodeSame(403);
    }

    private function createProviderThenLoginAsReadOnlyUser(): int
    {
        $this->login();
        $id = $this->createProvider('Anthropic production');
        $this->logout();

        $this->loginAsReadOnlyUser();

        return $id;
    }

    private function loginAsReadOnlyUser(): void
    {
        $username = 'ai-provider-reader-' . bin2hex(random_bytes(6));
        $this->grantTopologyPages(
            $this->connection,
            $this->createNonAdminContact($this->connection, $username),
            self::AI_PROVIDERS_TOPOLOGY_PAGES,
            self::ACL_ACCESS_READ_ONLY,
            $username,
        );
        $this->login($username);
    }
}
