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

namespace App\AiConfiguration\Domain\Llm;

use Webmozart\Assert\Assert;

/**
 * Token usage of an LLM call, as reported by the provider.
 * Counts may be zero when the provider does not report usage (e.g. while streaming).
 */
final readonly class LlmUsage
{
    public function __construct(
        public int $inputTokens,
        public int $outputTokens,
    ) {
        Assert::greaterThanEq($inputTokens, 0);
        Assert::greaterThanEq($outputTokens, 0);
    }
}
