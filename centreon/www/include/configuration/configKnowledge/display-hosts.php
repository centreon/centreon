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

$modules_path = $centreon_path . 'www/include/configuration/configKnowledge/';
require_once $modules_path . 'functions.php';
require_once $centreon_path . '/bootstrap.php';
$pearDB = $dependencyInjector['configuration_db'];

if (! isset($limit) || (int) $limit < 0) {
    $limit = $centreon->optGen['maxViewConfiguration'];
}

$orderBy = 'host_name';
$order = 'ASC';

// Use whitelist as we can't bind ORDER BY sort parameter
if (! empty($_POST['order']) && in_array($_POST['order'], ['ASC', 'DESC'])) {
    $order = $_POST['order'];
}

require_once './include/common/autoNumLimit.php';

// Add paths
set_include_path(get_include_path() . PATH_SEPARATOR . $modules_path);

require_once $centreon_path . '/www/class/centreon-knowledge/procedures.class.php';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($modules_path);

try {
    $postHost = ! empty($_POST['searchHost'])
        ? HtmlAnalyzer::sanitizeAndRemoveTags($_POST['searchHost'])
        : '';
    $postHostgroup = ! empty($_POST['searchHostgroup'])
        ? filter_input(INPUT_POST, 'searchHostgroup', FILTER_VALIDATE_INT)
        : false;
    $postPoller = ! empty($_POST['searchPoller'])
        ? filter_input(INPUT_POST, 'searchPoller', FILTER_VALIDATE_INT)
        : false;
    $searchHasNoProcedure = ! empty($_POST['searchHasNoProcedure'])
        ? HtmlAnalyzer::sanitizeAndRemoveTags($_POST['searchHasNoProcedure'])
        : '';
    $templatesHasNoProcedure = ! empty($_POST['searchTemplatesWithNoProcedure'])
        ? HtmlAnalyzer::sanitizeAndRemoveTags($_POST['searchTemplatesWithNoProcedure'])
        : '';

    $conf = getWikiConfig($pearDB);
    $WikiURL = $conf['kb_wiki_url'];

    $currentPage = 'hosts';
    require_once $modules_path . 'search.php';

    // Init Status Template
    $status = [
        0 => "<font color='orange'> " . _('No wiki page defined') . ' </font>',
        1 => "<font color='green'> " . _('Wiki page defined') . ' </font>',
    ];
    $proc = new procedures($pearDB);
    $proc->fetchProcedures();

    $queryValues = [];
    $query = '
        SELECT SQL_CALC_FOUND_ROWS host_name, host_id, host_register, ehi_icon_image
        FROM extended_host_information ehi, host ';

    if ($postPoller !== false) {
        $query .= 'JOIN ns_host_relation nhr ON nhr.host_host_id = host.host_id ';
    }
    if ($postHostgroup !== false) {
        $query .= 'JOIN hostgroup_relation hgr ON hgr.host_host_id = host.host_id ';
    }
    $query .= 'WHERE host.host_id = ehi.host_host_id ';
    if ($postPoller !== false) {
        $query .= 'AND nhr.nagios_server_id = :postPoller ';
        $queryValues[':postPoller'] = [
            PDO::PARAM_INT => $postPoller,
        ];
    }
    $query .= "AND host.host_register = '1' ";
    if ($postHostgroup !== false) {
        $query .= 'AND hgr.hostgroup_hg_id = :postHostgroup ';
        $queryValues[':postHostgroup'] = [
            PDO::PARAM_INT => $postHostgroup,
        ];
    }
    if (! empty($postHost)) {
        $query .= 'AND host_name LIKE :postHost ';
        $queryValues[':postHost'] = [
            PDO::PARAM_STR => '%' . $postHost . '%',
        ];
    }
    $query .= 'ORDER BY ' . $orderBy . ' ' . $order . ' LIMIT :offset, :limit';

    $statement = $pearDB->prepare($query);
    foreach ($queryValues as $bindId => $bindData) {
        foreach ($bindData as $bindType => $bindValue) {
            $statement->bindValue($bindId, $bindValue, $bindType);
        }
    }
    $statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    $rows = $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

    $selection = [];
    while ($data = $statement->fetch(PDO::FETCH_ASSOC)) {
        if ($data['host_register'] == 1) {
            $selection[$data['host_name']] = $data['host_id'];
        }
    }
    $statement->closeCursor();
    unset($data);

    // Create Diff
    $tpl->assign('host_name', _('Hosts'));

    $diff = [];
    $templateHostArray = [];

    foreach ($selection as $key => $value) {
        $tplStr = '';
        $tplArr = $proc->getMyHostMultipleTemplateModels($value);
        $diff[$key] = $proc->hostHasProcedure($key, $tplArr) == true ? 1 : 0;
        if (! empty($templatesHasNoProcedure)) {
            if ($diff[$key] == 1 || $proc->hostHasProcedure($key, $tplArr, PROCEDURE_INHERITANCE_MODE) == true) {
                $rows--;
                unset($diff[$key]);
                continue;
            }
        } elseif (! empty($searchHasNoProcedure)) {
            if ($diff[$key] == 1) {
                $rows--;
                unset($diff[$key]);
                continue;
            }
        }

        if (count($tplArr)) {
            $firstTpl = 1;
            foreach ($tplArr as $key1 => $value1) {
                if ($firstTpl) {
                    $tplStr .= "<a href='" . $WikiURL
                        . '/index.php?title=Host-Template_:_' . $value1 . "' target='_blank'>" . $value1 . '</a>';
                    $firstTpl = 0;
                } else {
                    $tplStr .= "&nbsp;|&nbsp;<a href='" . $WikiURL
                        . '/index.php?title=Host-Template_:_' . $value1 . "' target='_blank'>" . $value1 . '</a>';
                }
            }
        }
        $templateHostArray[$key] = $tplStr;
        unset($tplStr);
    }

    include './include/common/checkPagination.php';

    if (isset($templateHostArray)) {
        $tpl->assign('templateHostArray', $templateHostArray);
    }

    $WikiVersion = getWikiVersion($WikiURL . '/api.php');
    $tpl->assign('WikiVersion', $WikiVersion);
    $tpl->assign('WikiURL', $WikiURL);
    $tpl->assign('content', $diff);
    $tpl->assign('status', $status);
    $tpl->assign('selection', 0);

    // Send template in order to open

    // translations
    $tpl->assign('status_trans', _('Status'));
    $tpl->assign('actions_trans', _('Actions'));
    $tpl->assign('template_trans', _('Template'));

    // Template
    $tpl->registerObject('lineTemplate', getLineTemplate('list_one', 'list_two'));
    $tpl->assign('limit', $limit);

    $tpl->assign('order', $order);
    $tpl->assign('orderby', $orderBy);
    $tpl->assign('defaultOrderby', 'host_name');

    // Apply a template definition
    $tpl->display($modules_path . 'templates/display.ihtml');
} catch (Exception $e) {
    $tpl->assign('errorMsg', $e->getMessage());
    $tpl->display($modules_path . 'templates/NoWiki.tpl');
}
