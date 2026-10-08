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

namespace App\MonitoringConfiguration\Infrastructure\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Fields a partial update may leave out but never send as null: the value is required once the key is
 * there. The Input DTO cannot see the difference, so the keys are read from the request body.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class NotNullWhenProvided extends Constraint
{
    public string $message = 'This value cannot be null.';

    /**
     * @param list<string> $keys the snake_case keys of the request body
     * @param ?list<string> $groups
     */
    public function __construct(public array $keys = [], ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
