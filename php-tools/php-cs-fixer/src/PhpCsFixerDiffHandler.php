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

namespace Tools\PhpCsFixer;

final class PhpCsFixerDiffHandler
{
    /**
     * @param array<string> $sections
     * @param array<string, array{files: array<string>, directories: array<string>, skip: array<string>}> $pathsConfig
     * @param array<int, string> $args
     */
    public function __construct(
        private readonly string $moduleName,
        private readonly array $sections,
        private readonly array $pathsConfig,
        private array $args,
    ) {
    }

    public function handle(): void
    {
        $toFix = true;
        if (in_array('--dry-run', $this->args, true)) {
            $toFix = false;
        }

        echo '################################################' . PHP_EOL;
        echo '=> Preparing files to analyse' . PHP_EOL;

        $diffFiles = [];

        foreach ($this->args as $key => $arg) {
            if (str_starts_with($arg, $this->moduleName . '/') && str_ends_with($arg, '.php')) {
                $diffFiles[] = str_replace($this->moduleName . '/', '', $arg);
            }
            if (str_starts_with($arg, $this->moduleName . '/')) {
                unset($this->args[$key]);
            }
        }

        if ($diffFiles === []) {
            echo 'No files to analyse!' . PHP_EOL;

            exit(0);
        }

        $pathsToAnalyze = [];
        foreach ($this->sections as $section) {
            $pathsToAnalyze[$section] = [];
        }

        foreach ($diffFiles as $file) {
            $matched = false;
            foreach (array_keys($pathsToAnalyze) as $section) {
                if ($this->matchesConfig($file, $this->pathsConfig[$section])) {
                    $pathsToAnalyze[$section][] = $file;
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                echo '⚠️ File not recognised: ' . $file . PHP_EOL;
            }
        }

        foreach ($pathsToAnalyze as $section => $files) {
            $this->executeCsFixer("cs:{$section}", $files, $toFix);
        }
    }

    /**
     * @param array{files: array<string>, directories: array<string>, skip: array<string>} $config
     */
    private function matchesConfig(string $file, array $config): bool
    {
        return array_filter(
            array_merge($config['directories'], $config['files']),
            fn ($path): bool => str_starts_with($file, $path)
        ) !== []
            && array_filter(
                $config['skip'],
                fn ($skip): bool => str_starts_with($file, $skip)
            ) === [];
    }

    /**
     * @param array<int,string> $filesToAnalyze
     */
    private function executeCsFixer(string $commandName, array $filesToAnalyze, bool $toFix): void
    {
        echo '################################################' . PHP_EOL;
        echo '=> Running ' . $commandName . PHP_EOL;

        if ($filesToAnalyze !== []) {
            if ($toFix) {
                $commandName .= ':fix';
            }
            echo 'Files to analyse:' . PHP_EOL . implode(
                PHP_EOL,
                array_map(fn ($f): string => '- ' . $f, $filesToAnalyze)
            ) . PHP_EOL;
            $command = 'composer ' . $commandName . ' -- --format=txt --show-progress=none ' . implode(
                ' ',
                $filesToAnalyze
            );
            passthru($command, $exitCode);
            echo $exitCode === 0 ? '✔️ No errors!' . PHP_EOL : '❌ Errors found!' . PHP_EOL;

            exit($exitCode);
        }

        echo 'No files to analyse!' . PHP_EOL;
    }
}
