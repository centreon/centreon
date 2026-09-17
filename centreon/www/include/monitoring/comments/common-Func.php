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

if (! isset($centreon)) {
    exit();
}

function DeleteComment($type = null, $hosts = [])
{
    if (! isset($type) || ! is_array($hosts)) {
        return;
    }
    global $pearDB;
    $type = HtmlAnalyzer::sanitizeAndRemoveTags($type ?? '');

    foreach ($hosts as $key => $value) {
        $res = preg_split("/\;/", $key);
        $res[1] = filter_var($res[1] ?? 0, FILTER_VALIDATE_INT);
        write_command(' DEL_' . $type . '_COMMENT;' . $res[1], GetMyHostPoller($pearDB, $res[0]));
    }
}

function AddHostComment($host, $comment, $persistant)
{
    global $centreon, $pearDB;

    if (! isset($persistant) || ! in_array($persistant, ['0', '1'])) {
        $persistant = '0';
    }
    write_command(' ADD_HOST_COMMENT;' . getMyHostName($host) . ';' . $persistant . ';'
        . $centreon->user->get_alias() . ';' . trim($comment), GetMyHostPoller($pearDB, getMyHostName($host)));
}

function AddSvcComment($host, $service, $comment, $persistant)
{
    global $centreon, $pearDB;

    if (! isset($persistant) || ! in_array($persistant, ['0', '1'])) {
        $persistant = '0';
    }
    write_command(' ADD_SVC_COMMENT;' . getMyHostName($host) . ';' . getMyServiceName($service) . ';' . $persistant
        . ';' . $centreon->user->get_alias() . ';' . trim($comment), GetMyHostPoller($pearDB, getMyHostName($host)));
}
