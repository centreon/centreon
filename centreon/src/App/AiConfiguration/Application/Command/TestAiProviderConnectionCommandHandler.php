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

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Domain\Exception\AiProviderNotFoundException;
use App\AiConfiguration\Domain\Exception\InvalidAiProviderConfigurationException;
use App\AiConfiguration\Domain\Llm\ConnectionSpec;
use App\AiConfiguration\Domain\Llm\Exception\ApiTokenUnavailableException;
use App\AiConfiguration\Domain\Llm\Exception\LlmConfigurationException;
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmClientFactoryInterface;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Repository\AiProviderRepository;
use App\Shared\Application\Command\AsCommandHandler;

#[AsCommandHandler]
final readonly class TestAiProviderConnectionCommandHandler
{
    private const TOKEN_REQUIRED_MESSAGE = 'Enter the API token to test this endpoint.';

    public function __construct(
        private AiProviderRepository $repository,
        private LlmClientFactoryInterface $clientFactory,
    ) {
    }

    /**
     * @throws AiProviderNotFoundException when the provider to reuse does not exist
     * @throws ApiTokenUnavailableException when the stored token can not be read from the vault
     * @throws InvalidAiProviderConfigurationException when the endpoint is not acceptable for the type
     * @throws LlmConfigurationException
     */
    public function __invoke(TestAiProviderConnectionCommand $command): TestAiProviderResult
    {
        $apiToken = $command->apiToken;
        if (! $apiToken instanceof ApiToken) {
            if (! $command->id instanceof AiProviderId) {
                return new TestAiProviderResult(success: false, message: 'No API token to test.');
            }
            // editing with an empty token field: test the stored token, but only
            // against the stored target
            $stored = $this->repository->getById($command->id);
            if (! $stored->canReuseApiTokenFor($command->type, $command->endpointUrl)) {
                return new TestAiProviderResult(success: false, message: self::TOKEN_REQUIRED_MESSAGE);
            }
            $storedApiToken = $stored->apiToken();
        } else {
            $storedApiToken = StoredApiToken::plain($apiToken);
        }

        $spec = new ConnectionSpec(
            $command->type,
            $command->endpointUrl,
            $storedApiToken,
            $command->model,
            $command->modelReasoning,
        );

        try {
            // a refusal still proves the connection works: the provider answered
            $response = $this->clientFactory->forConnectionTest($spec)
                ->complete(LlmRequest::connectionTest($command->model));
        } catch (LlmRequestFailedException $exception) {
            return new TestAiProviderResult(success: false, message: $exception->getMessage());
        }

        return TestAiProviderResult::fromResponse($response);
    }
}
