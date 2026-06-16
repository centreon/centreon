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

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProvider;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderName;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Domain\Llm\Exception\LlmException;
use App\AiConfiguration\Domain\Llm\LlmClientFactoryInterface;
use App\AiConfiguration\Domain\Repository\AiProviderRepository;
use App\Shared\Application\Command\AsCommandHandler;

#[AsCommandHandler]
final readonly class DiscoverModelsCommandHandler
{
    public function __construct(
        private AiProviderRepository $repository,
        private LlmClientFactoryInterface $clientFactory,
    ) {
    }

    public function __invoke(DiscoverModelsCommand $command): DiscoverModelsResult
    {
        $apiToken = $command->apiToken;
        if (! $apiToken instanceof ApiToken) {
            if ($command->id === null) {
                // nothing to authenticate with (the form gates this anyway)
                return new DiscoverModelsResult(models: []);
            }
            // editing with an empty token field: use the stored token
            $apiToken = $this->repository->getById($command->id)->apiToken;
        }

        $aiProvider = new AiProvider(
            id: $command->id,
            name: new AiProviderName('model-discovery'),
            type: $command->type,
            endpointUrl: $command->endpointUrl,
            apiToken: $apiToken,
            // unused by listModels(), but the aggregate requires a default model
            defaultModel: new ModelName('model-discovery'),
            fastModel: null,
            description: null,
            isActivated: false,
        );

        try {
            $models = $this->clientFactory->forConnectionTest($aiProvider)->listModels();
        } catch (LlmException $exception) {
            // an expected provider error (bad token, unreachable, ...) is
            // returned as data, like the connection-test handlers, so it does
            // not propagate through the command bus as a CRITICAL failure; the
            // processor turns it into a 422
            return new DiscoverModelsResult(models: [], error: $exception->getMessage());
        }

        return new DiscoverModelsResult(models: $models);
    }
}
