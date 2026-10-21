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

namespace CentreonLegacy\Core\Process;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * A console of the code base, run by the current PHP binary.
 */
final class PhpConsole
{
    /**
     * No timeout: a cache warmup or a migration can be slow on a small host.
     *
     * @param list<string> $arguments the console, then its arguments
     * @param array<string, string>|null $env
     */
    public static function process(array $arguments, ?array $env = null): Process
    {
        return new Process([(new PhpExecutableFinder())->find(false) ?: 'php', ...$arguments], \dirname(__DIR__, 4), $env, null, null);
    }
}
