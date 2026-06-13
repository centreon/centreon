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
use App\AiConfiguration\Application\Command\TestAiProviderConnectionCommand;
use App\AiConfiguration\Application\Command\TestAiProviderResult;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderTypeEnum;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\EndpointUrl;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use App\AiConfiguration\Infrastructure\AiFeatureAvailability;
use App\AiConfiguration\Infrastructure\ApiPlatform\Dto\TestConnectionInput;
use App\AiConfiguration\Infrastructure\ApiPlatform\Resource\AiProvider\TestConnectionResource;
use App\Shared\Application\Command\CommandBus;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<TestConnectionInput, TestConnectionResource>
 */
final readonly class TestConnectionProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private AiFeatureAvailability $availability,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): TestConnectionResource
    {
        $this->availability->assertAvailable();

        $type = AiProviderTypeEnum::from($data->type);

        // the endpoint of curated providers is enforced, like create/update
        $endpointUrl = $type->defaultEndpointUrl()
            ?? ($data->endpointUrl !== null
                ? new EndpointUrl($data->endpointUrl)
                : throw new UnprocessableEntityHttpException(
                    sprintf("An endpoint URL is required for providers of type '%s'.", $type->value)
                ));

        $typedToken = $data->apiToken !== null && trim($data->apiToken) !== ''
            ? new ApiToken($data->apiToken)
            : null;

        $result = $this->commandBus->execute(new TestAiProviderConnectionCommand(
            type: $type,
            model: new ModelName($data->defaultModel),
            endpointUrl: $endpointUrl,
            apiToken: $typedToken,
            id: $data->providerId !== null ? new AiProviderId($data->providerId) : null,
        ));
        Assert::isInstanceOf($result, TestAiProviderResult::class);

        return new TestConnectionResource(
            success: $result->success,
            message: $result->message,
            model: $result->model,
        );
    }
}
