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

namespace App\MonitoringConfiguration\Domain\Aggregate\Command;

use Webmozart\Assert\Assert;

final readonly class CommandLine
{
    public function __construct(
        public string $value,
    ) {
        Assert::lengthBetween($value, 1, 65535);
    }

    /**
     * @return array<string> unique argument names (e.g. ['ARG1', 'ARG2'])
     */
    public function extractArguments(): array
    {
        preg_match_all('/\$(ARG\d+)\$/', $this->value, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @return array<string> unique host macro names (e.g. ['SOME_MACRO'])
     */
    public function extractHostMacros(): array
    {
        preg_match_all('/\$_HOST([\w_-]+)\$/', $this->value, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @return array<string> unique service macro names (e.g. ['SOME_MACRO'])
     */
    public function extractServiceMacros(): array
    {
        preg_match_all('/\$_SERVICE([\w_-]+)\$/', $this->value, $matches);

        return array_values(array_unique($matches[1]));
    }
}
