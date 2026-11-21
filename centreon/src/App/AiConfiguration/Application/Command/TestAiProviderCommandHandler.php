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

namespace App\AiConfiguration\Application\Command;

use App\AiConfiguration\Domain\Exception\AiProviderNotFoundException;
use App\AiConfiguration\Domain\Llm\Exception\ApiTokenUnavailableException;
use App\AiConfiguration\Domain\Llm\Exception\LlmConfigurationException;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmClientFactoryInterface;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Repository\AiProviderRepository;
use App\Shared\Application\Command\AsCommandHandler;

#[AsCommandHandler]
final readonly class TestAiProviderCommandHandler
{
    public function __construct(
        private AiProviderRepository $repository,
        private LlmClientFactoryInterface $clientFactory,
    ) {
    }

    /**
     * @throws AiProviderNotFoundException when the provider to test does not exist
     * @throws ApiTokenUnavailableException when the stored token can not be read from the vault
     * @throws LlmConfigurationException when the spend limit of the provider is reached
     */
    public function __invoke(TestAiProviderCommand $command): TestAiProviderResult
    {
        $aiProvider = $this->repository->getById($command->id);

        $client = $this->clientFactory->forProvider($aiProvider);

        try {
            $response = $client->complete(LlmRequest::connectionTest($command->model));
        } catch (LlmRequestFailedException $exception) {
            // an expected provider error is the test result; a spend limit
            // reached or a platform failure (vault) propagates
            return new TestAiProviderResult(success: false, message: $exception->getMessage());
        }

        return new TestAiProviderResult(success: true, model: $response->model);
    }
}
