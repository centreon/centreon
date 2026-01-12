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

/**
 * @param string $command
 * @return bool
 */
function isCommandInAllowedResources(CentreonDB $pearDB, string $command): bool
{
    $allowedResources = getAllResources($pearDB);
    foreach ($allowedResources as $path) {
        if (str_starts_with($command, $path)) {
            return true;
        }
    }

    return false;
}

/**
 * @param CentreonDB $pearDB
 * @param int $commandId
 * @return string|null
 */
function getCommandById(CentreonDB $pearDB, int $commandId): ?string
{
    $sth = $pearDB->prepare('SELECT command_line FROM `command` WHERE `command_id` = :command_id');
    $sth->bindParam(':command_id', $commandId, PDO::PARAM_INT);
    $sth->execute();
    $command = $sth->fetchColumn();

    return $command !== false ? $command : null;
}

/**
 * @param CentreonDB $pearDB
 * @param string $resourceName
 * @return string|null
 */
function getResourcePathByName(CentreonDB $pearDB, string $resourceName): ?string
{
    $prepare = $pearDB->prepare(
        'SELECT `resource_line` FROM `cfg_resource` WHERE `resource_name` = :resource LIMIT 1'
    );
    $prepare->bindValue(':resource', $resourceName, PDO::PARAM_STR);
    $prepare->execute();
    $resourcePath = $prepare->fetchColumn();

    return $resourcePath !== false ? $resourcePath : null;
}

/**
 * @param CentreonDB $pearDB
 * @return string[]
 */
function getAllResources(CentreonDB $pearDB): array
{
    $dbResult = $pearDB->query('SELECT `resource_line` FROM `cfg_resource`');

    return $dbResult->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * @param string $commandLine
 * @return array{commandPath:string,plugin:string|null,mode:string|null}
 */
function getCommandElements(string $commandLine): array
{
    $commandElements = explode(' ', $commandLine);

    $matchPluginOption = array_values(preg_grep('/^\-\-plugin\=(\w+)/i', $commandElements) ?? []);
    $plugin = $matchPluginOption[0] ?? null;
    $matchModeOption = array_values(preg_grep('/^\-\-mode\=(\w+)/i', $commandElements) ?? []);
    $mode = $matchModeOption[0] ?? null;

    return ['commandPath' => $commandElements[0], 'plugin' => $plugin, 'mode' => $mode];
}

/**
 * @param CentreonDB $pearDB
 * @param string $commandPath
 * @return string
 */
function replaceMacroInCommandPath(CentreonDB $pearDB, string $commandPath): string
{
    $explodedCommandPath = explode('/', $commandPath);
    $resourceName = $explodedCommandPath[0];

    // Match if the first part of the path is a MACRO
    if ($resourcePath = getResourcePathByName($pearDB, $resourceName)) {
        unset($explodedCommandPath[0]);

        return rtrim($resourcePath, '/') . '/' . implode('/', $explodedCommandPath);
    }

    return $commandPath;
}
