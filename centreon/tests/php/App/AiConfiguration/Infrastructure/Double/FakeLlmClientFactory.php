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

namespace Tests\App\AiConfiguration\Infrastructure\Double;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Llm\Exception\LlmConfigurationException;
use App\AiConfiguration\Domain\Llm\LlmClientFactoryInterface;
use App\AiConfiguration\Domain\Llm\LlmClientInterface;

final class FakeLlmClientFactory implements LlmClientFactoryInterface
{
    public function __construct(
        private readonly ?LlmClientInterface $client = null,
        private readonly ?LlmConfigurationException $exception = null,
    ) {
    }

    public function forActivatedProvider(): LlmClientInterface
    {
        return $this->getClientOrThrow();
    }

    public function forProvider(AiProvider $aiProvider): LlmClientInterface
    {
        return $this->getClientOrThrow();
    }

    private function getClientOrThrow(): LlmClientInterface
    {
        if ($this->exception instanceof LlmConfigurationException) {
            throw $this->exception;
        }

        return $this->client ?? new FakeLlmClient();
    }
}
