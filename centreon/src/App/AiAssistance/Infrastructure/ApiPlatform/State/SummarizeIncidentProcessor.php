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
use App\AiAssistance\Application\Command\IncidentEvent;
use App\AiAssistance\Application\Command\SummarizeIncidentCommand;
use App\AiAssistance\Application\Command\SummarizeIncidentResult;
use App\AiAssistance\Infrastructure\ApiPlatform\Dto\IncidentEventInput;
use App\AiAssistance\Infrastructure\ApiPlatform\Dto\SummarizeIncidentInput;
use App\AiAssistance\Infrastructure\ApiPlatform\Resource\SummarizeIncidentResource;
use App\Shared\Application\Command\CommandBus;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<SummarizeIncidentInput, SummarizeIncidentResource>
 */
final readonly class SummarizeIncidentProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): SummarizeIncidentResource
    {
        $result = $this->commandBus->execute(new SummarizeIncidentCommand(
            name: $data->name,
            resourceType: $data->resourceType,
            status: $data->status,
            events: array_map(
                static fn (IncidentEventInput $event): IncidentEvent => new IncidentEvent(
                    date: $event->date,
                    type: $event->type,
                    content: $event->content,
                    author: $event->author,
                    status: $event->status,
                ),
                $data->events
            ),
            information: $data->information,
            duration: $data->duration,
            parentName: $data->parentName,
            since: $data->since,
            isTruncated: $data->isTruncated,
        ));
        Assert::isInstanceOf($result, SummarizeIncidentResult::class);

        return new SummarizeIncidentResource(
            summary: $result->summary,
            model: $result->model,
        );
    }
}
