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
 * @template T
 */
interface DifferenceInterface
{
    /**
     * @param array<T> $before
     * @param array<T> $after
     */
    public function __construct(array $before, array $after);

    /**
     * @return array<T>
     */
    public function getAdded(): array;

    /**
     * @return array<T>
     */
    public function getRemoved(): array;

    /**
     * @return array<T>
     */
    public function getCommon(): array;
}