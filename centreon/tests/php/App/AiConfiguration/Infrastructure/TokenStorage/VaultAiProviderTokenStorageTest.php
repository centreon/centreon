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

namespace Tests\App\AiConfiguration\Infrastructure\TokenStorage;

use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Infrastructure\TokenStorage\VaultAiProviderTokenStorage;
use App\Shared\Application\Vault\VaultCredentialWriter;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Double\FakeVault;

final class VaultAiProviderTokenStorageTest extends TestCase
{
    private const STORED_TOKEN = 'secret::vault::configuration/aiProviders/abc-123::_AI_API_TOKEN';

    private FakeVault $vault;

    private VaultAiProviderTokenStorage $storage;

    protected function setUp(): void
    {
        $this->vault = new FakeVault();
        $this->storage = new VaultAiProviderTokenStorage($this->vault, new VaultCredentialWriter($this->vault));
    }

    public function testTheTokenStaysInPlaintextWhenTheVaultIsDisabled(): void
    {
        $this->vault->vaultEnabled = false;

        $stored = $this->storage->persist(new ApiToken('a-token'), null);

        self::assertSame('a-token', $stored->value);
        self::assertFalse($stored->isVaultReference());
        self::assertSame([], $this->vault->writeManyCalls);
    }

    public function testTheTokenIsWrittenToTheVault(): void
    {
        $stored = $this->storage->persist(new ApiToken('a-token'), null);

        self::assertTrue($stored->isVaultReference());
        self::assertSame('secret::vault::configuration/aiProviders/new-uuid::_AI_API_TOKEN', $stored->value);
        self::assertSame(
            [['customPath' => 'configuration/aiProviders', 'secrets' => ['_AI_API_TOKEN' => 'a-token'], 'uuid' => null, 'deletes' => []]],
            $this->vault->writeManyCalls
        );
    }

    public function testRotatingTheTokenReusesTheProviderEntry(): void
    {
        $stored = $this->storage->persist(new ApiToken('b-token'), new StoredApiToken(self::STORED_TOKEN));

        self::assertSame(self::STORED_TOKEN, $stored->value);
        self::assertSame('abc-123', $this->vault->writeManyCalls[0]['uuid']);
        self::assertSame(['_AI_API_TOKEN' => 'b-token'], $this->vault->writeManyCalls[0]['secrets']);
    }

    public function testAPlaintextTokenMovesToAFreshEntry(): void
    {
        $this->storage->persist(new ApiToken('b-token'), new StoredApiToken('a-plaintext-token'));

        self::assertNull($this->vault->writeManyCalls[0]['uuid']);
    }

    public function testAWriteFailurePropagates(): void
    {
        $this->vault->writeThrows = true;

        $this->expectException(\RuntimeException::class);

        $this->storage->persist(new ApiToken('a-token'), null);
    }

    public function testRemovingAPlaintextTokenLeavesTheVaultAlone(): void
    {
        $this->storage->remove(new StoredApiToken('a-token'));

        self::assertSame([], $this->vault->deleteCalls);
    }

    public function testRemovingAStoredTokenDeletesItsEntry(): void
    {
        $this->storage->remove(new StoredApiToken(self::STORED_TOKEN));

        self::assertSame([['customPath' => 'configuration/aiProviders', 'uuid' => 'abc-123']], $this->vault->deleteCalls);
    }
}
