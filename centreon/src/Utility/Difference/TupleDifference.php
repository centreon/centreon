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
 * Only for TUPLE = array of INT or STRING.
 *
 * Note: indexes are preserved, up to you to use them or not.
 *
 * @template T of array<int|string>
 *
 * @implements DifferenceInterface<T>
 */
final class TupleDifference implements DifferenceInterface
{
    /** @var array<string> */
    private array $beforeSerialized;

    /** @var array<string> */
    private array $afterSerialized;

    /**
     * @param array<T> $before
     * @param array<T> $after
     */
    public function __construct(private readonly array $before, private readonly array $after)
    {
        $this->beforeSerialized = [];
        foreach ($this->before as $key => $value) {
            $this->beforeSerialized[$key] = serialize($value);
        }

        $this->afterSerialized = [];
        foreach ($this->after as $key => $value) {
            $this->afterSerialized[$key] = serialize($value);
        }
    }

    /**
     * @return array<T>
     */
    public function getAdded(): array
    {
        $diff = array_diff($this->afterSerialized, $this->beforeSerialized);

        $added = [];
        foreach ($diff as $key => $value) {
            $added[$key] = $this->after[$key];
        }

        return $added;
    }

    /**
     * @return array<T>
     */
    public function getRemoved(): array
    {
        $diff = array_diff($this->beforeSerialized, $this->afterSerialized);

        $removed = [];
        foreach ($diff as $key => $value) {
            $removed[$key] = $this->before[$key];
        }

        return $removed;
    }

    /**
     * @return array<T>
     */
    public function getCommon(): array
    {
        $diff = array_intersect($this->beforeSerialized, $this->afterSerialized);

        $common = [];
        foreach ($diff as $key => $value) {
            $common[$key] = $this->before[$key];
        }

        return $common;
    }
}