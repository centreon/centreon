<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Tools\PhpStan\CustomRules;

use Centreon\Domain\Log\LoggerTrait;

/**
 * This trait implements checkIfInUseCase method to check if a file is
 * a Use Case.
 */
trait CentreonRuleTrait
{
    /** @var array<string>|null */
    private ?array $loggerTraitMethods = null;

    /**
     * This method creates a Reflection of Logger Trait, extract the list of its methods
     * and returns them as array of strings.
     *
     * @return string[]
     */
    public function getLoggerTraitMethods(): array
    {
        if (null === $this->loggerTraitMethods) {
            $this->loggerTraitMethods = [];

            $methods = (new \ReflectionClass(LoggerTrait::class))->getMethods();
            foreach ($methods as $method) {
                $this->loggerTraitMethods[] = $method->name;
            }
        }

        return $this->loggerTraitMethods;
    }

    /**
     * Tells whether the class FQCN extends an Exception.
     *
     * @param ?string $classFqcn
     *
     * @return bool
     */
    private function extendsAnException(?string $classFqcn): bool
    {
        return ! empty($classFqcn) && is_a($classFqcn, \Exception::class, true);
    }

    /**
     * Tells whether the class short name or FQCN is valid for a Repository.
     *
     * @param string $className
     *
     * @return null|non-empty-string
     */
    private function getRepositoryName(string $className): ?string
    {
        return preg_match('/(?:^|\\\\)([A-Z][a-zA-Z]+)Repository$/', $className, $matches)
            ? ($matches[1] ?: null) : null;
    }

    /**
     * Tells whether the class short name or FQCN is valid for a Repository Interface.
     *
     * @param string $className
     *
     * @return null|non-empty-string
     */
    private function getRepositoryInterfaceName(string $className): ?string
    {
        return preg_match('/(?:^|\\\\)([A-Z][a-zA-Z]+)RepositoryInterface$/', $className, $matches)
            ? ($matches[1] ?: null) : null;
    }

    /**
     * This method checks if a file is a Use Case.
     *
     * @param string $filename
     *
     * @return bool
     */
    private function fileIsUseCase(string $filename): bool
    {
        $slash = '[/\\\\]';
        $useCase = preg_quote(preg_replace("#^.*{$slash}(\w+)\.php$#", '$1', $filename), '#');
        $pattern = '#'
            . "{$slash}UseCase"
            . "{$slash}(\w+{$slash})*{$useCase}"
            . "{$slash}{$useCase}\.php"
            . '$#';

        return (bool) preg_match($pattern, $filename);
    }
}
