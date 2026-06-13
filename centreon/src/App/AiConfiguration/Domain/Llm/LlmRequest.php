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

use App\AiConfiguration\Domain\Aggregate\AiProvider\ModelName;
use Webmozart\Assert\Assert;

final readonly class LlmRequest
{
    /**
     * @param list<LlmMessage> $messages
     * @param AiFeatureEnum $feature the feature issuing the request, recorded on the usage log
     * @param ModelName|null $model when null, the model serving the requested
     *                              tier in the provider configuration is used
     */
    public function __construct(
        public array $messages,
        public AiFeatureEnum $feature,
        public ?string $systemPrompt = null,
        public ?ModelName $model = null,
        public LlmModelTierEnum $tier = LlmModelTierEnum::Default,
        public int $maxTokens = 1024,
    ) {
        Assert::notEmpty($messages);
        Assert::allIsInstanceOf($messages, LlmMessage::class);
        Assert::positiveInteger($maxTokens);
        if ($systemPrompt !== null) {
            Assert::stringNotEmpty($systemPrompt);
        }
    }
}
