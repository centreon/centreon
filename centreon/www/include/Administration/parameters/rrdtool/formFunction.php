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

/**
 * Get the version of rrdtool
 *
 * @param string $rrdtoolBin The full path of rrdtool
 * @return string
 */
function getRrdtoolVersion($rrdtoolBin = null)
{
    if (is_null($rrdtoolBin) || ! is_executable($rrdtoolBin)) {
        return '';
    }
    $output = [];
    $retval = 0;
    @exec($rrdtoolBin, $output, $retval);
    if ($retval != 0) {
        return '';
    }
    $ret = preg_match('/^RRDtool ((\d\.?)+).*$/', $output[0], $matches);
    if ($ret === false || $ret === 0) {
        return '';
    }

    return $matches[1];
}

/**
 * Validate if only one rrdcached options is set
 *
 * @param array $values rrdcached_port and rrdcached_unix_path
 * @return bool
 */
function rrdcached_valid($values)
{
    return ! (trim($values[0]) != '' && trim($values[1]) != '');
}

function rrdcached_has_option($values)
{
    if (isset($values[0]['rrdcached_enable']) && $values[0]['rrdcached_enable'] == 1) {
        if (trim($values[1]) == '' && trim($values[2]) == '') {
            return false;
        }
    }

    return true;
}
