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

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Infrastructure\TokenStorage\LoggingAiProviderTokenStorage;
use App\AiConfiguration\Infrastructure\TokenStorage\VaultAiProviderTokenStorage;
use App\Shared\Application\Vault\VaultCredentialWriter;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Double\FakeVault;
use Tests\App\Shared\Infrastructure\Messenger\Double\LoggerSpy;

final class LoggingAiProviderTokenStorageTest extends TestCase
{
    private const STORED_TOKEN = 'secret::vault::configuration/aiProviders/abc-123::_AI_API_TOKEN';

    private FakeVault $vault;

    private LoggerSpy $logger;

    private LoggingAiProviderTokenStorage $storage;

    protected function setUp(): void
    {
        $this->vault = new FakeVault();
        $this->logger = new LoggerSpy();
        $this->storage = new LoggingAiProviderTokenStorage(
            new VaultAiProviderTokenStorage($this->vault, new VaultCredentialWriter($this->vault)),
            $this->logger,
        );
    }

    public function testAWriteFailurePropagates(): void
    {
        $this->vault->writeThrows = true;

        $this->expectException(\RuntimeException::class);

        $this->storage->persist(new ApiToken('a-token'));
    }

    public function testARemovalFailurePropagates(): void
    {
        $this->vault->deleteThrows = true;

        $this->expectException(\RuntimeException::class);

        $this->storage->remove(new StoredApiToken(self::STORED_TOKEN), new AiProviderId(1));
    }

    public function testARemovedEntryThatCanNotBeFoundIsReported(): void
    {
        $this->vault->extractedUuids[self::STORED_TOKEN] = null;

        $this->storage->remove(new StoredApiToken(self::STORED_TOKEN), new AiProviderId(7));

        self::assertSame([], $this->vault->deleteCalls);
        self::assertCount(1, $this->logger->warningMessages);
        self::assertSame(7, $this->logger->warningMessages[0]['context']['ai_provider_id']);
        self::assertStringNotContainsString(self::STORED_TOKEN, json_encode($this->logger->warningMessages, \JSON_THROW_ON_ERROR));
    }

    public function testDiscardingAStoredTokenDeletesItsEntry(): void
    {
        $this->storage->discard(new StoredApiToken(self::STORED_TOKEN), null);

        self::assertSame([['customPath' => 'configuration/aiProviders', 'uuid' => 'abc-123']], $this->vault->deleteCalls);
        self::assertSame([], $this->logger->warningMessages);
    }

    public function testADiscardFailureIsReportedWithoutThrowing(): void
    {
        $this->vault->deleteThrows = true;

        $this->storage->discard(new StoredApiToken(self::STORED_TOKEN), new AiProviderId(7));

        self::assertCount(1, $this->logger->warningMessages);
        self::assertSame(7, $this->logger->warningMessages[0]['context']['ai_provider_id']);
    }

    public function testDiscardingAnEntryThatCanNotBeFoundIsReportedWithoutThrowing(): void
    {
        $this->vault->extractedUuids[self::STORED_TOKEN] = null;

        $this->storage->discard(new StoredApiToken(self::STORED_TOKEN), null);

        self::assertCount(1, $this->logger->warningMessages);
    }
}
