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

namespace App\MonitoringConfiguration\Domain\Exception;

use App\MonitoringConfiguration\Domain\Aggregate\EngineConfiguration\MiscOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;

final class InvalidServiceNameException extends \RuntimeException
{
    /**
     * @param string $reason what is wrong with the name, phrased to follow its subject
     */
    private function __construct(public readonly string $reason)
    {
        parent::__construct(sprintf('The service name %s.', $reason));
    }

    public static function empty(): self
    {
        return new self('is empty');
    }

    public static function tooLong(): self
    {
        return new self(sprintf('is longer than %d characters', ServiceName::MAX_LENGTH));
    }

    public static function forbiddenCharacter(): self
    {
        return new self(sprintf(
            'contains a forbidden character (%s)',
            implode(' ', mb_str_split(MiscOptions::DEFAULT_ILLEGAL_OBJECT_NAME_CHARS)),
        ));
    }

    public static function controlCharacter(): self
    {
        return new self('contains a control character');
    }
}
