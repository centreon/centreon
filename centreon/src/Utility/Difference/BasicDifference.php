<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Utility\Difference;

/**
 * Only for INT or STRING : basic.
 *
 * Note: indexes are preserved, up to you to use them or not.
 *
 * @implements DifferenceInterface<int|string>
 */
final class BasicDifference implements DifferenceInterface
{
    /**
     * @param array<int|string> $before
     * @param array<int|string> $after
     */
    public function __construct(private readonly array $before, private readonly array $after)
    {
    }

    /**
     * @return array<int|string>
     */
    public function getAdded(): array
    {
        return array_diff($this->after, $this->before);
    }

    /**
     * @return array<int|string>
     */
    public function getRemoved(): array
    {
        return array_diff($this->before, $this->after);
    }

    /**
     * @return array<int|string>
     */
    public function getCommon(): array
    {
        return array_intersect($this->before, $this->after);
    }
}