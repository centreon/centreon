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

namespace App\AiConfiguration\Infrastructure\TokenStorage;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Domain\Repository\AiProviderTokenStorage;
use App\Shared\Infrastructure\Logging\ExceptionFormatter;
use Psr\Log\LoggerInterface;

/**
 * Reports the token storage failures that only leave an unused vault entry behind,
 * instead of failing the configuration change.
 */
final readonly class LoggingAiProviderTokenStorage implements AiProviderTokenStorage
{
    public function __construct(
        private VaultAiProviderTokenStorage $storage,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws \Throwable when the token cannot be written to its storage
     */
    public function persist(ApiToken $token): StoredApiToken
    {
        return $this->storage->persist($token);
    }

    /**
     * @throws \Throwable when the token cannot be removed from its storage
     */
    public function remove(StoredApiToken $token, AiProviderId $aiProviderId): void
    {
        try {
            $this->storage->remove($token);
        } catch (VaultEntryNotFoundException) {
            $this->logger->warning('Unable to find the vault entry of an AI provider API token, it is left in the vault', [
                'ai_provider_id' => $aiProviderId->value,
            ]);
        }
    }

    public function discard(StoredApiToken $token, ?AiProviderId $aiProviderId): void
    {
        try {
            $this->storage->remove($token);
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to remove an unused AI provider API token from the vault', [
                'ai_provider_id' => $aiProviderId?->value,
                'exception' => ExceptionFormatter::format($exception),
            ]);
        }
    }
}
