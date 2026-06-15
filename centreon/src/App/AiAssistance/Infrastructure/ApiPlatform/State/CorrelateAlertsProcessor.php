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
use App\AiAssistance\Application\Command\CorrelateAlertsCommand;
use App\AiAssistance\Application\Command\CorrelateAlertsResult;
use App\AiAssistance\Application\Command\CorrelatedResource;
use App\AiAssistance\Infrastructure\ApiPlatform\Dto\CorrelateAlertsInput;
use App\AiAssistance\Infrastructure\ApiPlatform\Dto\CorrelatedResourceInput;
use App\AiAssistance\Infrastructure\ApiPlatform\Resource\CorrelateAlertsResource;
use App\Shared\Application\Command\CommandBus;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<CorrelateAlertsInput, CorrelateAlertsResource>
 */
final readonly class CorrelateAlertsProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): CorrelateAlertsResource
    {
        $result = $this->commandBus->execute(new CorrelateAlertsCommand(
            resources: array_map(
                static fn (CorrelatedResourceInput $resource): CorrelatedResource => new CorrelatedResource(
                    name: $resource->name,
                    resourceType: $resource->resourceType,
                    status: $resource->status,
                    parentName: $resource->parentName,
                    information: $resource->information,
                    duration: $resource->duration,
                    tries: $resource->tries,
                    isAcknowledged: $resource->isAcknowledged,
                    isInDowntime: $resource->isInDowntime,
                ),
                $data->resources
            ),
        ));
        Assert::isInstanceOf($result, CorrelateAlertsResult::class);

        return new CorrelateAlertsResource(
            analysis: $result->analysis,
            model: $result->model,
        );
    }
}
