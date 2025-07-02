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
    exit;
}

require_once $centreon_path . '/bootstrap.php';
$pearDB = $dependencyInjector['configuration_db'];

$searchOptions = [
    'host' => 0,
    'service' => 0,
    'hostTemplate' => 0,
    'serviceTemplate' => 0,
    'poller' => 0,
    'hostgroup' => 0,
    'servicegroup' => 0,
    'hasNoProcedure' => 0,
    'templatesWithNoProcedure' => 0,
];

$labels = [
    'host' => _('Host'),
    'service' => _('Service'),
    'hostTemplate' => _('Host Template'),
    'serviceTemplate' => _('Service Template'),
    'poller' => _('Poller'),
    'hostgroup' => _('Hostgroup'),
    'servicegroup' => _('Servicegroup'),
    'hasNoProcedure' => _('Show wiki pageless only'),
    'templatesWithNoProcedure' => _('Show wiki pageless only - inherited templates included'),
    'search' => _('Search'),
];

if ($currentPage  == 'hosts') {
    $searchOptions['host'] = 1;
    $searchOptions['poller'] = 1;
    $searchOptions['hostgroup'] = 1;
    $searchOptions['hasNoProcedure'] = 1;
    $searchOptions['templatesWithNoProcedure'] = 1;
} elseif ($currentPage == 'services') {
    $searchOptions['host'] = 1;
    $searchOptions['service'] = 1;
    $searchOptions['poller'] = 1;
    $searchOptions['hostgroup'] = 1;
    $searchOptions['servicegroup'] = 1;
    $searchOptions['hasNoProcedure'] = 1;
    $searchOptions['templatesWithNoProcedure'] = 1;
} elseif ($currentPage == 'hostTemplates') {
    $searchOptions['hostTemplate'] = 1;
    $searchOptions['hasNoProcedure'] = 1;
    $searchOptions['templatesWithNoProcedure'] = 1;
} elseif ($currentPage == 'serviceTemplates') {
    $searchOptions['serviceTemplate'] = 1;
    $searchOptions['hasNoProcedure'] = 1;
    $searchOptions['templatesWithNoProcedure'] = 1;
}

$tpl->assign('searchHost', $postHost ?? '');
$tpl->assign('searchService', $postService ?? '');
$tpl->assign('searchHostTemplate', $postHostTemplate ?? '');
$tpl->assign(
    'searchServiceTemplate',
    $postServiceTemplate ?? ''
);

$checked = '';
if (! empty($searchHasNoProcedure)) {
    $checked = 'checked';
}
$tpl->assign('searchHasNoProcedure', $checked);

$checked2 = '';
if (! empty($templatesHasNoProcedure)) {
    $checked2 = 'checked';
}
$tpl->assign('searchTemplatesWithNoProcedure', $checked2);

/**
 * Get Poller List
 */
if ($searchOptions['poller']) {
    $res = $pearDB->query(
        'SELECT id, name FROM nagios_server ORDER BY name'
    );
    $searchPoller = "<option value='0'></option>";
    while ($row = $res->fetchRow()) {
        if (
            isset($postPoller)
            && $postPoller !== false
            && $row['id'] == $postPoller
        ) {
            $searchPoller .= "<option value='" . $row['id'] . "' selected>" . $row['name'] . '</option>';
        } else {
            $searchPoller .= "<option value='" . $row['id'] . "'>" . $row['name'] . '</option>';
        }
    }
    $tpl->assign('searchPoller', $searchPoller);
}

/**
 * Get Hostgroup List
 */
if ($searchOptions['hostgroup']) {
    $res = $pearDB->query(
        'SELECT hg_id, hg_name FROM hostgroup ORDER BY hg_name'
    );
    $searchHostgroup = "<option value='0'></option>";
    while ($row = $res->fetchRow()) {
        if (
            isset($postHostgroup)
            && $postHostgroup !== false
            && $row['hg_id'] == $postHostgroup
        ) {
            $searchHostgroup .= "<option value ='" . $row['hg_id'] . "' selected>" . $row['hg_name'] . '</option>';
        } else {
            $searchHostgroup .= "<option value ='" . $row['hg_id'] . "'>" . $row['hg_name'] . '</option>';
        }
    }
    $tpl->assign('searchHostgroup', $searchHostgroup);
}

/**
 * Get Servicegroup List
 */
if ($searchOptions['servicegroup']) {
    $res = $pearDB->query(
        'SELECT sg_id, sg_name FROM servicegroup ORDER BY sg_name'
    );
    $searchServicegroup = "<option value='0'></option>";
    while ($row = $res->fetchRow()) {
        if (
            isset($postServicegroup)
            && $postServicegroup !== false
            && $row['sg_id'] == $postServicegroup
        ) {
            $searchServicegroup .= "<option value ='" . $row['sg_id'] . "' selected>" . $row['sg_name'] . '</option>';
        } else {
            $searchServicegroup .= "<option value ='" . $row['sg_id'] . "'>" . $row['sg_name'] . '</option>';
        }
    }
    $tpl->assign('searchServicegroup', $searchServicegroup);
}

$tpl->assign('labels', $labels);
$tpl->assign('searchOptions', $searchOptions);
