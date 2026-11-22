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
use Tests\App\Shared\Double\FakeVault;

/**
 * The stored token of a provider can not be read back from the vault: an
 * upstream dependency failed, which the API reports as a 502.
 */
final class AiProviderVaultFailureTest extends AiProviderApiTestCase
{
    private const STORED_TOKEN = 'secret::vault::configuration/aiProviders/abc-123::_AI_API_TOKEN';

    public function testAnUnreadableStoredTokenReturns502(): void
    {
        $id = $this->addProviderWithAVaultStoredToken();
        $this->useFailingVault();
        $this->login();

        $this->request('POST', self::ENDPOINT . '/' . $id . '/test', ['json' => []]);

        self::assertResponseStatusCodeSame(502);
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
        $vault = new FakeVault();
        $vault->resolveThrows = true;
        self::getContainer()->set(VaultInterface::class, $vault);
    }
}
