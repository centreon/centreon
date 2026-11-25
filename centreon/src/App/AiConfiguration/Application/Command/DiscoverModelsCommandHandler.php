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
use App\AiConfiguration\Domain\Llm\Exception\LlmRequestFailedException;
use App\AiConfiguration\Domain\Llm\LlmClientFactoryInterface;
use App\AiConfiguration\Domain\Repository\AiProviderRepository;
use App\Shared\Application\Command\AsCommandHandler;

#[AsCommandHandler]
final readonly class DiscoverModelsCommandHandler
{
    private const TOKEN_REQUIRED_MESSAGE = 'Enter the API token to list the models of this endpoint.';

    public function __construct(
        private AiProviderRepository $repository,
        private LlmClientFactoryInterface $clientFactory,
    ) {
    }

    /**
     * @throws AiProviderNotFoundException when the provider to reuse does not exist
     * @throws ApiTokenUnavailableException when the stored token can not be read from the vault
     * @throws InvalidAiProviderConfigurationException when the endpoint is not acceptable for the type
     */
    public function __invoke(DiscoverModelsCommand $command): DiscoverModelsResult
    {
        $apiToken = $command->apiToken;
        // editing with an empty token field: use the stored token (if any), but only
        // against the stored target
        $stored = ! $apiToken instanceof ApiToken && $command->id instanceof AiProviderId
            ? $this->repository->getById($command->id)
            : null;
        if ($apiToken instanceof ApiToken) {
            $storedApiToken = StoredApiToken::plain($apiToken);
        } elseif ($stored?->canReuseApiTokenFor($command->type, $command->endpointUrl) === true) {
            $storedApiToken = $stored->apiToken();
        } elseif (! $command->type->requiresApiToken()) {
            // a server that may need none is asked without one
            $storedApiToken = null;
        } elseif ($command->id instanceof AiProviderId) {
            return new DiscoverModelsResult(models: [], error: self::TOKEN_REQUIRED_MESSAGE);
        } else {
            // nothing to authenticate with (the form gates this anyway)
            return new DiscoverModelsResult(models: []);
        }

        $spec = new ConnectionSpec($command->type, $command->endpointUrl, $storedApiToken);

        try {
            $models = $this->clientFactory->forConnectionTest($spec)->listModels();
        } catch (LlmRequestFailedException $exception) {
            // an expected provider error (bad token, unreachable, ...) is
            // returned as data, like the connection-test handlers; the
            // processor turns it into a 422. A platform failure (vault)
            // propagates.
            return new DiscoverModelsResult(models: [], error: $exception->getMessage());
        }

        return new DiscoverModelsResult(models: $models);
    }
}
