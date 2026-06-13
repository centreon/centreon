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

namespace App\AiConfiguration\Domain\Usage;

use App\AiConfiguration\Domain\Llm\AiFeatureEnum;
use App\AiConfiguration\Domain\Llm\LlmModelTierEnum;

/**
 * One recorded LLM call: raw token counts plus the cost computed at write time
 * (so a later price change never rewrites history). The provider name and the
 * currency are snapshots, kept even if the provider is later deleted or changed.
 */
final readonly class UsageLogEntry
{
    public function __construct(
        public ?int $providerId,
        public string $providerName,
        public AiFeatureEnum $feature,
        public ?int $contactId,
        public string $model,
        public LlmModelTierEnum $tier,
        public int $inputTokens,
        public int $outputTokens,
        public ?float $cost,
        public ?string $currency,
        public int $requestedAt,
    ) {
    }
}
