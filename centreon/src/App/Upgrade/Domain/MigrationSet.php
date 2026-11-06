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

/**
 * A set of migrations versioned on its own: a Centreon Web database, or a module.
 */
final readonly class MigrationSet
{
    public const string WEB_TABLE = 'doctrine_migration_versions';

    // A module name maps to a single metadata table, and to a PHP namespace.
    public const string MODULE_NAME_RULE = 'lowercase letters and digits, starting with a letter, in words separated by single dashes, '
        . 'at most ' . self::MODULE_NAME_MAX_LENGTH . ' characters';
    private const string MODULE_NAME_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/D';

    // Its metadata table name must fit the shortest identifier limit of the usual databases (63, PostgreSQL).
    private const int MODULE_NAME_MAX_LENGTH = 35;

    private function __construct(
        public string $name,
        public ?WebMigrationSetEnum $webSet = null,
        public ?string $module = null,
    ) {
    }

    public static function web(WebMigrationSetEnum $webSet): self
    {
        return new self($webSet->value, $webSet);
    }

    /**
     * @return list<self> in execution order
     */
    public static function webSets(): array
    {
        return array_map(self::web(...), WebMigrationSetEnum::cases());
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function module(string $module): self
    {
        if (! self::isModuleName($module)) {
            throw new \InvalidArgumentException(sprintf('Unsupported module name "%s": %s.', $module, self::MODULE_NAME_RULE));
        }

        return new self('module:' . $module, module: $module);
    }

    public static function isModuleName(string $module): bool
    {
        return preg_match(self::MODULE_NAME_PATTERN, $module) === 1 && mb_strlen($module) <= self::MODULE_NAME_MAX_LENGTH;
    }

    /**
     * The Centreon Web sets share one metadata table, each module has its own.
     */
    public function table(): string
    {
        return $this->module === null ? self::WEB_TABLE : self::WEB_TABLE . '_' . str_replace('-', '_', $this->module);
    }

    /**
     * The upgrade step that migrates this set, as the upgrade log names it.
     */
    public function migrationStep(): string
    {
        return 'migrations:' . $this->name;
    }

    /**
     * The upgrade step that rolls this set back, as the upgrade log names it.
     */
    public function rollbackStep(): string
    {
        return 'migrations_rollback:' . $this->name;
    }

    /**
     * Human-readable name, for console output.
     */
    public function label(): string
    {
        return $this->module === null ? sprintf('"%s" migration set', $this->name) : sprintf('"%s" module migration set', $this->module);
    }

    public function isModule(): bool
    {
        return $this->module !== null;
    }
}
