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

$pathsConfig = require_once __DIR__ . '/rector.conf.php';

$args = $_SERVER['argv'] ?? null;

$toFix = true;
if (in_array('--dry-run', $args, true)) {
    $toFix = false;
}

echo '################################################' . PHP_EOL;
echo '=> Preparing files to analyse' . PHP_EOL;

$diffFiles = [];

for ($i = 0, $iMax = count($args); $i < $iMax; $i++) {
    if (str_starts_with((string) $args[$i], 'centreon-dsm/') && str_ends_with((string) $args[$i], '.php')) {
        $diffFiles[] = str_replace('centreon-dsm/', '', $args[$i]);
    }
    if (str_starts_with((string) $args[$i], 'centreon-dsm/')) {
        unset($args[$i]);
    }
}

if ($diffFiles === []) {
    echo 'No files to analyse!' . PHP_EOL;

    exit(0);
}

$pathsLegacyToAnalyze = [];

foreach ($diffFiles as $file) {
    if (
        array_filter($pathsConfig['legacy']['paths'], fn ($path): bool => str_starts_with($file, (string) $path)) !== []
        && array_filter($pathsConfig['legacy']['skip'], fn ($skip): bool => str_starts_with($file, (string) $skip)
        ) === []
    ) {
        $pathsLegacyToAnalyze[] = $file;
    } else {
        echo 'File not recognised: ' . $file . PHP_EOL;
    }
}

executeRector('rector:legacy', $pathsLegacyToAnalyze, $toFix);

/**
 * @param string $commandName Can be rector:legacy, rector:core or rector:new
 * @param array $filesToAnalyze Files to analyze
 * @param bool $toFix To fix the errors or just display them
 */
function executeRector(string $commandName, array $filesToAnalyze, bool $toFix): void
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
        $command = 'composer ' . $commandName . ' -- --no-progress-bar ' . implode(' ', $filesToAnalyze);
        passthru($command);
    } else {
        echo 'No files to analyse!' . PHP_EOL;
    }
}
