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

function return_plugin($rep)
{
    global $centreon;

    $availableConnectors = [];
    $is_not_a_plugin = ['.' => 1, '..' => 1, 'oreon.conf' => 1, 'oreon.pm' => 1, 'utils.pm' => 1, 'negate' => 1, 'centreon.conf' => 1, 'centreon.pm' => 1];
    if (is_readable($rep)) {
        $handle[$rep] = opendir($rep);
        while (false != ($filename = readdir($handle[$rep]))) {
            if ($filename != '.' && $filename != '..') {
                if (is_dir($rep . $filename)) {
                    $plg_tmp = return_plugin($rep . '/' . $filename);
                    $availableConnectors = array_merge($availableConnectors, $plg_tmp);
                    unset($plg_tmp);
                } elseif (! isset($is_not_a_plugin[$filename])
                    && ! str_ends_with($filename, '~')
                    && ! str_ends_with($filename, '#')
                ) {
                    if (isset($oreon)) {
                        $key = substr($rep . '/' . $filename, strlen($oreon->optGen['cengine_path_connectors']));
                    } else {
                        $key = substr($rep . '/' . $filename, 0);
                    }

                    $availableConnectors[$key] = $key;
                }
            }
        }
        closedir($handle[$rep]);
    }

    return $availableConnectors;
}
