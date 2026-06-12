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

/**
 * Model tier requested by a feature. Which actual model serves a tier is
 * decided by the AI provider configuration: features only declare their
 * intent. The fast tier falls back to the default model when the
 * configuration does not define a fast model.
 */
enum LlmModelTierEnum: string
{
    case Default = 'default';
    case Fast = 'fast';
}
