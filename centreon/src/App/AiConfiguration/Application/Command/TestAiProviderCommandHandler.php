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

use App\AiConfiguration\Domain\Llm\AiFeatureEnum;
use App\AiConfiguration\Domain\Llm\Exception\LlmException;
use App\AiConfiguration\Domain\Llm\LlmClientFactoryInterface;
use App\AiConfiguration\Domain\Llm\LlmMessage;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmRoleEnum;
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

    public function __invoke(TestAiProviderCommand $command): TestAiProviderResult
    {
        $aiProvider = $this->repository->getById($command->id);

        try {
            $client = $this->clientFactory->forProvider($aiProvider);

            $response = $client->complete(new LlmRequest(
                messages: [new LlmMessage(LlmRoleEnum::User, 'Reply with the single word: pong')],
                feature: AiFeatureEnum::ConnectionTest,
                model: $command->model,
                maxTokens: 16,
            ));
        } catch (LlmException $exception) {
            return new TestAiProviderResult(success: false, message: $exception->getMessage());
        }

        return new TestAiProviderResult(success: true, model: $response->model);
    }
}
