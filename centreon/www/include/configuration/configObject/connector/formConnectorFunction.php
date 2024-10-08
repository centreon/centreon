<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

function return_plugin($rep)
{
    global $centreon;

    $availableConnectors = [];
    $is_not_a_plugin = ["." => 1, ".." => 1, "oreon.conf" => 1, "oreon.pm" => 1, "utils.pm" => 1, "negate" => 1, "centreon.conf" => 1, "centreon.pm" => 1];
    if (is_readable($rep)) {
        $handle[$rep] = opendir($rep);
        while (false != ($filename = readdir($handle[$rep]))) {
            if ($filename != "." && $filename != "..") {
                if (is_dir($rep.$filename)) {
                    $plg_tmp = return_plugin($rep."/".$filename);
                    $availableConnectors = array_merge($availableConnectors, $plg_tmp);
                    unset($plg_tmp);
                } elseif (!isset($is_not_a_plugin[$filename])
                    && !str_ends_with($filename, "~")
                    && !str_ends_with($filename, "#")
                ) {
                    if (isset($oreon)) {
                        $key = substr($rep."/".$filename, strlen($oreon->optGen["cengine_path_connectors"]));
                    } else {
                        $key = substr($rep."/".$filename, 0);
                    }

                    $availableConnectors[$key] = $key;
                }
            }
        }
        closedir($handle[$rep]);
    }
    return ($availableConnectors);
}
