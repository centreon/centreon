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

use App\MonitoringConfiguration\Domain\Aggregate\EngineConfiguration\MiscOptions;
use App\MonitoringConfiguration\Domain\Exception\InvalidServiceNameException;
use App\MonitoringConfiguration\Domain\Model\EngineConfigurationValue;
use Webmozart\Assert\Assert;

final readonly class ServiceName
{
    public const MIN_LENGTH = 1;

    // The size of the service_description column.
    public const MAX_LENGTH = 200;

    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        Assert::lengthBetween($value, self::MIN_LENGTH, self::MAX_LENGTH);
        $this->value = $value;
    }

    /**
     * A name checked as on service creation, then stored trimmed with its inner runs of spaces collapsed. The plain
     * constructor stays lenient: a stored name may predate these rules.
     *
     * @throws InvalidServiceNameException
     */
    public static function fromUserInput(string $value): self
    {
        if (trim($value) === '') {
            throw InvalidServiceNameException::empty();
        }
        if (strpbrk($value, MiscOptions::DEFAULT_ILLEGAL_OBJECT_NAME_CHARS) !== false) {
            throw InvalidServiceNameException::forbiddenCharacter();
        }
        // On the raw value, before the collapse would turn a tab or a line break into a space.
        if (preg_match(EngineConfigurationValue::CONTROL_CHARACTERS_PATTERN, $value) === 1) {
            throw InvalidServiceNameException::controlCharacter();
        }

        $value = trim((string) preg_replace('/\s{2,}/', ' ', $value));
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidServiceNameException::tooLong();
        }

        return new self($value);
    }
}
