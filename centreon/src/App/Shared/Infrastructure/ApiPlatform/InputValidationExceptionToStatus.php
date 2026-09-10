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

namespace App\Shared\Infrastructure\ApiPlatform;

use ApiPlatform\Validator\Exception\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-operation `exceptionToStatus` override for new write operations: input validation errors
 * are 422 Unprocessable Entity, not 400. The global `api_platform.yaml` mapping stays 400 for
 * already-shipped resources (Command, Poller, ServiceCategory) to avoid changing their released
 * behavior — pass this constant to any new `Post`/`Put`/`Patch` operation's `exceptionToStatus`
 * instead.
 */
final class InputValidationExceptionToStatus
{
    public const MAP = [
        ValidationException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ];
}
