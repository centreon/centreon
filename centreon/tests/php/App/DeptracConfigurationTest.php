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

namespace Tests\App;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class DeptracConfigurationTest extends TestCase
{
    private const APP_DIRECTORY = __DIR__ . '/../../../src/App';
    private const HEXA_CONFIG_FILE = __DIR__ . '/../../../deptrac_hexa.yaml';
    private const BC_CONFIG_FILE = __DIR__ . '/../../../deptrac_bc.yaml';

    public function testEveryBoundedContextIsDeclaredInDeptracHexaConfiguration(): void
    {
        $missing = array_values(array_diff($this->boundedContexts(), $this->hexaConfiguredBoundedContexts()));

        self::assertSame(
            [],
            $missing,
            'The following bounded contexts under src/App are missing from the "paths" list in '
                . 'deptrac_hexa.yaml: ' . implode(', ', $missing)
        );
    }

    public function testEveryBoundedContextIsDeclaredInDeptracBcConfiguration(): void
    {
        $missing = array_values(array_diff($this->boundedContexts(), $this->bcConfiguredBoundedContexts()));

        self::assertSame(
            [],
            $missing,
            'The following bounded contexts under src/App are missing from the "layers" list in '
                . 'deptrac_bc.yaml: ' . implode(', ', $missing)
        );
    }

    /**
     * @return list<string>
     */
    private function boundedContexts(): array
    {
        $directories = glob(self::APP_DIRECTORY . '/*', \GLOB_ONLYDIR);
        self::assertNotFalse($directories, 'Unable to list directories under src/App');

        return array_map('basename', $directories);
    }

    /**
     * @return list<string>
     */
    private function hexaConfiguredBoundedContexts(): array
    {
        /** @var array{parameters: array{paths: list<string>}} $config */
        $config = Yaml::parseFile(self::HEXA_CONFIG_FILE);

        $boundedContexts = [];
        foreach ($config['parameters']['paths'] as $path) {
            if (preg_match('#^\./src/App/([^/]+)$#', $path, $matches) === 1) {
                $boundedContexts[] = $matches[1];
            }
        }

        return $boundedContexts;
    }

    /**
     * @return list<string>
     */
    private function bcConfiguredBoundedContexts(): array
    {
        /** @var array{parameters: array{layers: list<array{name: string, collectors: list<array{type: string, value: string}>}>}} $config */
        $config = Yaml::parseFile(self::BC_CONFIG_FILE);

        $boundedContexts = [];
        foreach ($config['parameters']['layers'] as $layer) {
            foreach ($layer['collectors'] as $collector) {
                if (
                    $collector['type'] === 'directory'
                    && preg_match('#^src/App/([^/]+)/\.\*$#', $collector['value'], $matches) === 1
                ) {
                    $boundedContexts[] = $matches[1];
                }
            }
        }

        return $boundedContexts;
    }
}
