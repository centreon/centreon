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

namespace Core\Resources\Domain\Model;

/**
 * Opaque cursor for keyset (cursor) pagination of the resources endpoint.
 *
 * The cursor encodes the sort-column values of the last item on the current page plus the
 * resource_id tiebreaker. The repository uses these values to build a keyset WHERE condition
 * that replaces the LIMIT/OFFSET approach, making deep pagination O(1) instead of O(offset).
 *
 * Each entry in $sorts is: ['col' => '<resources table column>', 'dir' => 'ASC'|'DESC', 'val' => mixed]
 *
 * @phpstan-type _SortEntry array{col: string, dir: string, val: int|string}
 */
final class ResourceCursor
{
    /**
     * @param list<_SortEntry> $sorts
     * @param int $resourceId
     */
    public function __construct(
        public readonly array $sorts,
        public readonly int $resourceId,
    ) {
    }

    /**
     * Decode a base64-encoded cursor token produced by encode().
     *
     * @throws \InvalidArgumentException when the token is malformed
     */
    public static function decode(string $token): self
    {
        $decoded = base64_decode($token, strict: true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid cursor: base64 decode failed');
        }

        $data = json_decode($decoded, associative: true);
        if (
            ! is_array($data)
            || ! isset($data['sorts'], $data['rid'])
            || ! is_array($data['sorts'])
            || ! is_int($data['rid'])
        ) {
            throw new \InvalidArgumentException('Invalid cursor: unexpected structure');
        }

        /** @var list<array{col: string, dir: string, val: int|string}> $sorts */
        $sorts = array_values($data['sorts']);

        return new self($sorts, $data['rid']);
    }

    /**
     * Encode the cursor as an opaque base64 token safe for use in query strings.
     */
    public function encode(): string
    {
        return base64_encode((string) json_encode([
            'sorts' => $this->sorts,
            'rid' => $this->resourceId,
        ]));
    }
}
