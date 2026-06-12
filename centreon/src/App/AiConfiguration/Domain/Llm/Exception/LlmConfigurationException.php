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

/**
 * The LLM request can not be built from the AI provider configuration
 * (e.g. no default provider, unresolvable token).
 */
final class LlmConfigurationException extends LlmException
{
    public static function noDefaultProvider(): self
    {
        return new self('No default AI provider is configured.');
    }

    public static function unresolvableToken(): self
    {
        return new self('The API token of this AI provider can not be resolved.');
    }
}
