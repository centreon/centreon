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

namespace App\ActivityLogging\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiProperty;

final class ActivityLogTargetOutput
{
    public function __construct(
        #[ApiProperty(openapiContext: ['example' => 42])]
        public int $id,

        #[ApiProperty(openapiContext: ['example' => 'Service', 'enum' => ActivityLogResource::TARGET_TYPES])]
        public string $type,

        #[ApiProperty(openapiContext: ['example' => 'Ping'])]
        public string $name,

        #[ApiProperty(description: 'The host(s) or host group(s) of a service target, null otherwise or when unknown', genId: false)]
        public ?ActivityLogTargetParentOutput $parent,
    ) {
    }
}
