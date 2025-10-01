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

namespace Tools\Rector\Command;

use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

final readonly class RunRectorOnDiffCommand
{
    /**
     * @param array<string> $sections
     * @param array<string, array{paths: array<int, string>, skip: array<int, string>}> $pathsConfig
     * @param array<int, string> $args
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public string $moduleName,
        public array $sections,
        public array $pathsConfig,
        public array $args,
    ) {
        Assert::notEmpty($moduleName, 'Module name must be provided');
        Assert::notEmpty($sections, 'At least one section must be provided');
        Assert::notEmpty($pathsConfig, 'Paths configuration must be provided');
        Assert::notEmpty($args, 'Arguments must be provided');
        Assert::allString($sections, 'Sections must be strings');
        Assert::allString($args, 'Arguments must be strings');
        Assert::allInArray($sections, array_keys($pathsConfig), 'One or more sections are not present in paths configuration');
        foreach ($pathsConfig as $config) {
            Assert::keyExists($config, 'paths', 'Each config must have the "paths" key');
            Assert::keyExists($config, 'skip', 'Each config must have the "skip" key');
        }
    }
}
