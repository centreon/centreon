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

namespace App\Upgrade\Domain;

use Webmozart\Assert\Assert;

/**
 * A set of migrations versioned on its own: a core database, or a module.
 */
final readonly class MigrationSet
{
    public const string REALTIME = 'realtime';
    public const string CONFIGURATION = 'configuration';

    // Lowercase and dashes only: a module name maps to a single metadata table, and to a PHP namespace.
    private const string MODULE_NAME_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/D';

    // Its metadata table name must fit the 64 characters of a MySQL identifier.
    private const int MODULE_NAME_MAX_LENGTH = 36;

    private function __construct(
        public string $name,
        public ?string $module = null,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function core(string $name): self
    {
        Assert::oneOf($name, [self::REALTIME, self::CONFIGURATION], 'Unknown core migration set %s.');

        return new self($name);
    }

    /**
     * @return list<self> in execution order
     */
    public static function coreSets(): array
    {
        return [new self(self::REALTIME), new self(self::CONFIGURATION)];
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function module(string $module): self
    {
        if (! self::isModuleName($module)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported module name "%s": lowercase letters, digits and dashes only, at most %d characters.', $module, self::MODULE_NAME_MAX_LENGTH));
        }

        return new self('module:' . $module, $module);
    }

    public static function isModuleName(string $module): bool
    {
        return preg_match(self::MODULE_NAME_PATTERN, $module) === 1 && \mb_strlen($module) <= self::MODULE_NAME_MAX_LENGTH;
    }

    public function isModule(): bool
    {
        return $this->module !== null;
    }
}
