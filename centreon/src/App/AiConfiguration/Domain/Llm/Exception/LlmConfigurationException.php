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

namespace App\AiConfiguration\Domain\Llm\Exception;

use App\Shared\Domain\Exception\ExpectedFailureInterface;

/**
 * The AI provider configuration does not allow the LLM request (no enabled
 * provider, spend limit reached, no API token for a type that needs one).
 */
final class LlmConfigurationException extends LlmException implements ExpectedFailureInterface
{
    public static function noActivatedProvider(): self
    {
        return new self('No AI provider is enabled.');
    }

    public static function budgetExceeded(): self
    {
        return new self('The AI provider spend limit has been reached for the current period.');
    }

    /**
     * Only possible for a provider stored before the rule, or edited by hand.
     */
    public static function noApiToken(): self
    {
        return new self('This AI provider has no API token: enter one in its configuration.');
    }
}
