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

namespace Centreon\Infrastructure\Service\Traits;

use Centreon\Infrastructure\Service\Exception\NotFoundException;

trait ServiceContainerTrait
{
    /** @var array<string|int,mixed> */
    private $objects = [];

    public function has($id): bool
    {
        return array_key_exists(strtolower($id), $this->objects);
    }

    public function get($id): string
    {
        if ($this->has($id) === false) {
            throw new NotFoundException(sprintf(_('Not found exporter with name: %d'), $id));
        }

        return $this->objects[strtolower($id)];
    }

    public function all(): array
    {
        return $this->objects;
    }
}
