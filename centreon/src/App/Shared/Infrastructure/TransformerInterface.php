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

namespace App\Shared\Infrastructure;

/**
 * A transformer builds its whole output by itself, calling other transformers when needed.
 * $extraData carries only what it cannot resolve from $from on its own, such as a value computed
 * by the caller or lookups batched over a whole collection.
 *
 * Since $extraData defaults to [], every key of a TExtraData shape must be optional.
 *
 * @template TFrom
 * @template TTo
 * @template TExtraData of array<string, mixed> = array{}
 */
interface TransformerInterface
{
    /**
     * @param TFrom $from
     * @param TExtraData $extraData
     *
     * @return TTo
     */
    public function transform(mixed $from, array $extraData = []): mixed;
}
