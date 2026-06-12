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

namespace App\AiAssistance\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\AiAssistance\Application\Command\ExplainResourceCommand;
use App\AiAssistance\Application\Command\ExplainResourceResult;
use App\AiAssistance\Infrastructure\ApiPlatform\Dto\ExplainResourceInput;
use App\AiAssistance\Infrastructure\ApiPlatform\Resource\ExplainResourceResource;
use App\Shared\Application\Command\CommandBus;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<ExplainResourceInput, ExplainResourceResource>
 */
final readonly class ExplainResourceProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ExplainResourceResource
    {
        $result = $this->commandBus->execute(new ExplainResourceCommand(
            name: $data->name,
            resourceType: $data->resourceType,
            status: $data->status,
            information: $data->information,
            commandLine: $data->commandLine,
            performanceData: $data->performanceData,
            duration: $data->duration,
            tries: $data->tries,
            parentName: $data->parentName,
        ));
        Assert::isInstanceOf($result, ExplainResourceResult::class);

        return new ExplainResourceResource(
            explanation: $result->explanation,
            model: $result->model,
        );
    }
}
