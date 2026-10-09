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

namespace App\Shared\Infrastructure\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Of each given set of properties, at most one may be sent: a list is either replaced, added to or
 * removed from, never two of those at once.
 *
 * It looks at what the caller sent, not at the values, since an empty list and a key left out read
 * the same on the input.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ExclusiveKeys extends Constraint
{
    public string $message = 'Only one of {{ keys }} can be provided.';

    /**
     * @param list<list<string>> $sets sets of property names
     * @param string[]|null $groups
     */
    public function __construct(
        public array $sets,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(groups: $groups, payload: $payload);
    }

    /**
     * @return string[]
     */
    public function getTargets(): array
    {
        return [self::CLASS_CONSTRAINT];
    }
}
