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

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Infrastructure\Dbal\DbalAiProviderRepository;
use App\Shared\Domain\VaultInterface;
use Doctrine\DBAL\Connection;
use Tests\App\Shared\Double\FakeVault;

/**
 * The vault can not read back or write a provider token: an upstream
 * dependency failed, which the API reports as a 502.
 */
final class AiProviderVaultFailureTest extends AiProviderApiTestCase
{
    private const STORED_TOKEN = 'secret::vault::configuration/aiProviders/abc-123::_AI_API_TOKEN';
    private const SUBMITTED_TOKEN = 'a-submitted-token-that-never-leaks';

    public function testAnUnreadableStoredTokenReturns502(): void
    {
        $id = $this->addProviderWithAVaultStoredToken();
        $this->useFailingVault();
        $this->login();

        $this->request('POST', self::ENDPOINT . '/' . $id . '/test', ['json' => []]);

        self::assertResponseStatusCodeSame(502);
    }

    public function testAFailedVaultWriteOnCreationReturns502AndSavesNothing(): void
    {
        $vault = $this->useVault();
        $vault->writeThrows = true;
        $this->login();

        $response = $this->request('POST', self::ENDPOINT, [
            'json' => [
                'name' => 'Vault-backed provider',
                'type' => 'anthropic',
                'api_token' => self::SUBMITTED_TOKEN,
                'default_model' => 'claude-sonnet-4-6',
            ],
        ]);

        self::assertResponseStatusCodeSame(502);
        self::assertStringNotContainsString(self::SUBMITTED_TOKEN, $response->getContent(false));
        self::assertCount(1, $vault->writeManyCalls);
        self::assertSame([], $this->findRowsNamed('Vault-backed provider'));
    }

    public function testAFailedVaultWriteOnUpdateReturns502AndChangesNothing(): void
    {
        $id = $this->addProviderWithAVaultStoredToken();
        $rowsBefore = $this->findRowsNamed('Anthropic production');
        $vault = $this->useVault();
        $vault->writeThrows = true;
        $this->login();

        $response = $this->request('PATCH', self::ENDPOINT . '/' . $id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['default_model' => 'claude-opus-4-8', 'api_token' => self::SUBMITTED_TOKEN],
        ]);

        self::assertResponseStatusCodeSame(502);
        self::assertStringNotContainsString(self::SUBMITTED_TOKEN, $response->getContent(false));
        self::assertCount(1, $vault->writeManyCalls);
        self::assertCount(1, $rowsBefore);
        self::assertSame($rowsBefore, $this->findRowsNamed('Anthropic production'));
    }

    public function testAFailedVaultDeletionReturns502AndKeepsTheProvider(): void
    {
        $id = $this->addProviderWithAVaultStoredToken();
        $vault = $this->useVault();
        $vault->deleteThrows = true;
        $this->login();

        $this->request('DELETE', self::ENDPOINT . '/' . $id);

        self::assertResponseStatusCodeSame(502);
        self::assertCount(1, $vault->deleteCalls);
        self::assertCount(1, $this->findRowsNamed('Anthropic production'));
    }

    /**
     * Added through the repository: a vault reference can not be submitted through the API.
     */
    private function addProviderWithAVaultStoredToken(): int
    {
        /** @var DbalAiProviderRepository $repository */
        $repository = self::getContainer()->get(DbalAiProviderRepository::class);
        $aiProvider = new AiProvider(
            id: null,
            name: new AiProviderName('Anthropic production'),
            type: AiProviderTypeEnum::Anthropic,
            endpointUrl: new EndpointUrl('https://api.anthropic.com'),
            apiToken: new StoredApiToken(self::STORED_TOKEN),
            defaultModel: new ModelName('claude-sonnet-4-6'),
            fastModel: null,
            description: null,
            isActivated: true,
        );
        $repository->add($aiProvider);

        return $aiProvider->id()->value;
    }

    /**
     * Swapped in before the one request of the test: the kernel is rebooted between two requests.
     */
    private function useFailingVault(): void
    {
        $this->useVault()->resolveThrows = true;
    }

    /**
     * Swapped in before the one request of the test: the kernel is rebooted between two requests.
     */
    private function useVault(): FakeVault
    {
        $vault = new FakeVault();
        self::getContainer()->set(VaultInterface::class, $vault);

        return $vault;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findRowsNamed(string $name): array
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        return $connection->fetchAllAssociative(
            'SELECT * FROM ' . DbalAiProviderRepository::TABLE_NAME . ' WHERE name = :name',
            ['name' => $name],
        );
    }
}
