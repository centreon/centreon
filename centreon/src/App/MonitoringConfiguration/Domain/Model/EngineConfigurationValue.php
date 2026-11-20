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

namespace App\MonitoringConfiguration\Domain\Model;

/**
 * Rules shared by the values written as-is into the monitoring engine configuration.
 */
final class EngineConfigurationValue
{
    /**
     * ASCII control characters (line breaks, tabs, NUL, DEL). The engine configuration files hold
     * one directive per line: a line break inside a value would end its line early, and the engine
     * would read the rest as a directive of its own.
     */
    public const CONTROL_CHARACTERS_PATTERN = '/[\x00-\x1F\x7F]/';

    private function __construct()
    {
    }

    /**
     * Replaces each run of control characters with a space. The API rejects them on input; this
     * keeps a value stored with one (CLAPI, older data) loadable, on a single line.
     */
    public static function toSingleLine(string $value): string
    {
        return (string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value);
    }
}
