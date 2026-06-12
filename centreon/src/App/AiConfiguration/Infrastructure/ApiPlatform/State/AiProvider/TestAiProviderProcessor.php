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

namespace App\AiConfiguration\Infrastructure\ApiPlatform\State\AiProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\AiConfiguration\Application\Command\TestAiProviderCommand;
use App\AiConfiguration\Application\Command\TestAiProviderResult;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Infrastructure\AiFeatureAvailability;
use App\AiConfiguration\Infrastructure\ApiPlatform\Dto\TestAiProviderInput;
use App\AiConfiguration\Infrastructure\ApiPlatform\Resource\AiProvider\TestAiProviderResource;
use App\Shared\Application\Command\CommandBus;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<TestAiProviderInput, TestAiProviderResource>
 */
final readonly class TestAiProviderProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private AiFeatureAvailability $availability,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): TestAiProviderResource
    {
        $this->availability->assertAvailable();

        Assert::keyExists($uriVariables, 'id');
        Assert::integer($uriVariables['id']);

        $command = new TestAiProviderCommand(
            id: new AiProviderId($uriVariables['id']),
            model: $data->model !== null ? new ModelName($data->model) : null,
        );

        $result = $this->commandBus->execute($command);
        Assert::isInstanceOf($result, TestAiProviderResult::class);

        return new TestAiProviderResource(
            success: $result->success,
            message: $result->message,
            model: $result->model,
        );
    }
}
