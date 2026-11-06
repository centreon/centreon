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
use App\Shared\Infrastructure\ApiPlatform\RequestPayload;
use App\Shared\Infrastructure\Validator\Constraints\NotNullWhenProvided;

/**
 * Every key is optional: a key left out leaves the value untouched. The properties that cannot be null carry
 * {@see NotNullWhenProvided}, since this DTO reads both cases as null (see {@see RequestPayload}).
 *
 * Do not add an `id` property here: it would flip a missing-service 404 into a 422 via
 * InvalidReferenceExceptionListener.
 */
final readonly class PatchServiceInput
{
    public function __construct(
        #[ApiProperty(description: 'Whether the service is enabled.')]
        #[NotNullWhenProvided]
        public ?bool $activated = null,
    ) {
    }
}
