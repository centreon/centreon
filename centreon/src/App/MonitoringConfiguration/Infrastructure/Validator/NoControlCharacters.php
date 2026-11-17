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

namespace App\MonitoringConfiguration\Infrastructure\Validator;

use App\MonitoringConfiguration\Domain\Model\EngineConfigurationValue;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Compound;

/**
 * Rejects ASCII control characters (line breaks, tabs, NUL, DEL) in a value written as-is into the
 * monitoring engine configuration: see {@see EngineConfigurationValue::CONTROL_CHARACTERS_PATTERN}.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class NoControlCharacters extends Compound
{
    public const MESSAGE = 'This value must not contain control characters.';

    /**
     * @param array<mixed> $options
     *
     * @return list<Assert\Regex>
     */
    protected function getConstraints(array $options): array
    {
        return [new Assert\Regex(pattern: EngineConfigurationValue::CONTROL_CHARACTERS_PATTERN, message: self::MESSAGE, match: false)];
    }
}
