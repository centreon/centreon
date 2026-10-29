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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service;

use ApiPlatform\Metadata\ApiProperty;

/**
 * One listed (host, service) pair. The nested objects are plain arrays, not objects: JSON-LD would add its own
 * id and type keys to a nested object, while the wire shape must be exactly the keys below. A null property is
 * left out of the response.
 */
final readonly class ServiceCollectionOutput
{
    private const MEDIA_SCHEMA = [
        'type' => 'object',
        'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string'], 'url' => ['type' => 'string']],
    ];

    public function __construct(
        #[ApiProperty(identifier: true)]
        public int $id,

        public string $name,

        public bool $activated,

        /**
         * @var array{id: int, name: string, icon?: array{id: int, name: string, url: string}}
         */
        #[ApiProperty(openapiContext: [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'icon' => self::MEDIA_SCHEMA,
            ],
        ])]
        public array $host,

        /**
         * @var array{id: int, name: string, url: string}|null
         */
        #[ApiProperty(openapiContext: self::MEDIA_SCHEMA)]
        public ?array $icon = null,

        #[ApiProperty(description: 'Effective normal check interval, in engine interval units')]
        public ?int $normalCheckInterval = null,

        #[ApiProperty(description: 'Effective retry check interval, in engine interval units')]
        public ?int $retryCheckInterval = null,

        /**
         * @var array{id: int, name: string}|null
         */
        #[ApiProperty(openapiContext: [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string']],
        ])]
        public ?array $template = null,
    ) {
    }
}
