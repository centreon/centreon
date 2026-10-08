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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto;

use Symfony\Component\HttpFoundation\Request;

/**
 * The keys a caller actually sent in the request body. An Input DTO cannot tell a key left out from a
 * key sent as null (both read as null), and a partial update must: the first leaves the value alone,
 * the second clears it.
 */
final readonly class PatchHostPayload
{
    /**
     * @param array<array-key, mixed> $data the decoded body, snake_case keys as on the wire
     */
    private function __construct(private array $data)
    {
    }

    public static function fromRequest(?Request $request): self
    {
        if (! $request instanceof Request) {
            return new self([]);
        }

        try {
            return new self($request->toArray());
        } catch (\Throwable) {
            return new self([]);
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function isNull(string $key): bool
    {
        return array_key_exists($key, $this->data) && $this->data[$key] === null;
    }

    /**
     * The keys sent inside a sub-object, none when the sub-object is absent or not an object.
     */
    public function section(string $key): self
    {
        $section = $this->data[$key] ?? null;

        return new self(is_array($section) ? $section : []);
    }
}
