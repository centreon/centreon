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

namespace App\MonitoringConfiguration\Domain\Aggregate\Service;

use Webmozart\Assert\Assert;

final readonly class ServiceName
{
    public const MIN_LENGTH = 1;

    // The size of the service_description column.
    public const MAX_LENGTH = 200;
    public const FORBIDDEN_CHARACTERS = '/[~!$%^&*"|\'<>?,()=]/';
    public const CONTROL_CHARACTERS = '/[\x00-\x1F\x7F]/';

    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        Assert::lengthBetween($value, self::MIN_LENGTH, self::MAX_LENGTH);
        $this->value = $value;
    }

    /**
     * Whether the name holds a character a write refuses. Not an invariant: a name legacy stored with one is still
     * read.
     */
    public function hasForbiddenCharacters(): bool
    {
        return preg_match(self::FORBIDDEN_CHARACTERS, $this->value) === 1
            || preg_match(self::CONTROL_CHARACTERS, $this->value) === 1;
    }
}
