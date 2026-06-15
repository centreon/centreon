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
use App\AiAssistance\Application\Command\ExplainGraphCommand;
use App\AiAssistance\Application\Command\ExplainGraphResult;
use App\AiAssistance\Application\Command\GraphMetric;
use App\AiAssistance\Infrastructure\ApiPlatform\Dto\ExplainGraphInput;
use App\AiAssistance\Infrastructure\ApiPlatform\Dto\GraphMetricInput;
use App\AiAssistance\Infrastructure\ApiPlatform\Resource\ExplainGraphResource;
use App\Shared\Application\Command\CommandBus;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<ExplainGraphInput, ExplainGraphResource>
 */
final readonly class ExplainGraphProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ExplainGraphResource
    {
        $result = $this->commandBus->execute(new ExplainGraphCommand(
            name: $data->name,
            resourceType: $data->resourceType,
            metrics: array_map(
                static fn (GraphMetricInput $metric): GraphMetric => new GraphMetric(
                    name: $metric->name,
                    unit: $metric->unit,
                    minimum: $metric->minimum,
                    maximum: $metric->maximum,
                    average: $metric->average,
                    current: $metric->current,
                ),
                $data->metrics
            ),
            parentName: $data->parentName,
            timeRange: $data->timeRange,
            performanceData: $data->performanceData,
        ));
        Assert::isInstanceOf($result, ExplainGraphResult::class);

        return new ExplainGraphResource(
            explanation: $result->explanation,
            model: $result->model,
        );
    }
}
