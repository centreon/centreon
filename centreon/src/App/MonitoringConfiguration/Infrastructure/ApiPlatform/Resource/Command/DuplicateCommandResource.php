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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Command;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\DuplicateCommandInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command\DuplicateCommandsProcessor;

#[Post(
    shortName: 'Command',
    uriTemplate: '/configuration/commands/_duplicate',
    processor: DuplicateCommandsProcessor::class,
    input: DuplicateCommandInput::class,
    status: 200,
    errors: [],
    openapi: new Model\Operation(
        summary: 'Duplicate a Command resource',
        responses: [
            200 => new Model\Response(
                description: 'A collection holding the duplication outcome for each requested command',
                content: new \ArrayObject([
                    'application/ld+json' => new Model\MediaType(
                        schema: new \ArrayObject([
                            'type' => 'object',
                            'properties' => [
                                '@context' => ['type' => 'string'],
                                '@id' => ['type' => 'string'],
                                '@type' => ['type' => 'string', 'example' => 'Collection'],
                                'totalItems' => ['type' => 'integer'],
                                'member' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            '@id' => ['type' => 'string'],
                                            '@type' => ['type' => 'string', 'example' => 'DuplicateCommandResource'],
                                            'command' => [
                                                'type' => 'string',
                                                'description' => 'IRI of the duplicated command (absent when the duplication failed)',
                                            ],
                                            'status' => ['type' => 'integer'],
                                            'message' => ['type' => 'string'],
                                        ],
                                        'required' => ['@id', '@type', 'status', 'message'],
                                    ],
                                ],
                            ],
                        ]),
                        example: [
                            '@context' => '/centreon/api/latest/contexts/Command',
                            '@id' => '/centreon/api/latest/.well-known/genid/b69ebebaa3bb3de34dd8',
                            '@type' => 'Collection',
                            'totalItems' => 2,
                            'member' => [
                                [
                                    '@id' => '/centreon/api/latest/.well-known/genid/c2afa2366f6f99491863',
                                    '@type' => 'DuplicateCommandResource',
                                    'command' => '/centreon/api/latest/configuration/commands/98',
                                    'status' => 204,
                                    'message' => 'Command duplicated successfully',
                                ],
                                [
                                    '@id' => '/centreon/api/latest/.well-known/genid/3fa305a034165257c384',
                                    '@type' => 'DuplicateCommandResource',
                                    'status' => 404,
                                    'message' => 'Command with ID 99999 not found',
                                ],
                            ],
                        ],
                    ),
                ]),
            ),
            400 => new Model\Response(
                description: 'Invalid input — the request body failed validation',
                content: new \ArrayObject([
                    'application/json' => new Model\MediaType(
                        schema: new \ArrayObject([
                            'type' => 'object',
                            'properties' => [
                                'code' => ['type' => 'integer', 'example' => 400],
                                'message' => [
                                    'type' => 'string',
                                    'description' => 'One line per violation, formatted as "[propertyPath] message"',
                                    'example' => "[ids] IDs array cannot be empty\n",
                                ],
                            ],
                            'required' => ['code', 'message'],
                        ]),
                    ),
                ]),
            ),
            403 => new Model\Response(
                description: 'You are not allowed to duplicate commands',
                content: new \ArrayObject([
                    'application/json' => new Model\MediaType(
                        schema: new \ArrayObject([
                            'type' => 'object',
                            'properties' => [
                                'code' => ['type' => 'integer', 'example' => 0],
                                'message' => ['type' => 'string', 'example' => 'You are not allowed to duplicate commands'],
                            ],
                            'required' => ['code', 'message'],
                        ]),
                    ),
                ]),
            ),
        ],
    ),
)]
final readonly class DuplicateCommandResource
{
    public function __construct(
        #[ApiProperty(readableLink: false)]
        public ?CommandResource $command,

        #[ApiProperty(
            description: 'The status of the duplication operation',
            openapiContext: ['example' => 204]
        )]
        public int $status,

        #[ApiProperty(
            description: 'Descriptive message about the duplication result',
            openapiContext: ['example' => 'Command duplicated successfully']
        )]
        public string $message,
    ) {
    }
}
