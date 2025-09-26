<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

require_once 'rector.conf.php';

$args = $_SERVER['argv'] ?? null;

$toFix = true;
if (in_array('--dry-run', $args, true)) {
    $toFix = false;
}

$diffFiles = [];

for ($i = 0, $iMax = count($args); $i < $iMax; $i++) {
    if (str_starts_with($args[$i], 'centreon/') && str_ends_with($args[$i], '.php')) {
        $diffFiles[] = str_replace('centreon/', '', $args[$i]);
    }
    if (str_starts_with($args[$i], 'centreon/')) {
        unset($args[$i]);
    }
}

if (empty($diffFiles)) {
    echo 'No files to analyze' . PHP_EOL;
    exit(0);
}

$pathsLegacyToAnalyze = [];
$pathsCoreToAnalyze = [];
$pathsNewToAnalyze = [];

foreach ($diffFiles as $file) {
    if (
        array_filter($pathsLegacy, fn($pathLegacy) => str_starts_with($file, $pathLegacy)) !== []
        && array_filter($skipPathsLegacy, fn($skipPathLegacy) => str_starts_with($file, $skipPathLegacy)) === []
    ) {
        $pathsLegacyToAnalyze[] = $file;
    } elseif (array_filter($pathsCore, fn($pathCore) => str_starts_with($file, $pathCore)) !== []) {
        $pathsCoreToAnalyze[] = $file;
    } elseif (array_filter($pathsNew, fn($pathNew) => str_starts_with($file, $pathNew)) !== []) {
        $pathsNewToAnalyze[] = $file;
    } else {
        echo 'Path not recognized for rector: ' . $file . PHP_EOL;
    }
}

executeRector('rector:legacy', $pathsLegacyToAnalyze, $toFix);
executeRector('rector:core', $pathsCoreToAnalyze, $toFix);
executeRector('rector:new', $pathsNewToAnalyze, $toFix);

/**
 * @param string $commandName Can be rector:legacy, rector:core or rector:new
 * @param array $filesToAnalyze Files to analyze
 * @param bool $toFix To fix the errors or just display them
 *
 * @return void
 */
function executeRector(string $commandName, array $filesToAnalyze, bool $toFix): void
{
    if ($filesToAnalyze !== []) {
        if ($toFix) {
            $commandName = $commandName . ':fix';
        }
        echo 'Running ' . $commandName . ' on : ' . implode(', ', $filesToAnalyze) . PHP_EOL;
        $command = 'composer ' . $commandName . ' -- ' . implode(' ', $filesToAnalyze);
        passthru($command);
    } else {
        echo 'No paths to analyze with ' . $commandName . PHP_EOL;
    }
}
