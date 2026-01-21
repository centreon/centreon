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

$orderBy = 'service_description';
$order = 'ASC';

// Use whitelist as we can't bind ORDER BY values
if (! empty($_POST['order'])) {
    if (in_array($_POST['order'], ['ASC', 'DESC'])) {
        $order = $_POST['order'];
    }
}

require_once './include/common/autoNumLimit.php';

// Add paths
set_include_path(get_include_path() . PATH_SEPARATOR . $modules_path);

require_once $centreon_path . '/www/class/centreon-knowledge/procedures.class.php';

// Smarty template initialization
$tpl = SmartyBC::createSmartyTemplate($modules_path);

try {
    $postServiceTemplate = ! empty($_POST['searchServiceTemplate'])
        ? HtmlAnalyzer::sanitizeAndRemoveTags($_POST['searchServiceTemplate'])
        : '';
    $searchHasNoProcedure = ! empty($_POST['searchHasNoProcedure'])
        ? HtmlAnalyzer::sanitizeAndRemoveTags($_POST['searchHasNoProcedure'])
        : '';
    $templatesHasNoProcedure = ! empty($_POST['searchTemplatesWithNoProcedure'])
        ? HtmlAnalyzer::sanitizeAndRemoveTags($_POST['searchTemplatesWithNoProcedure'])
        : '';

    $conf = getWikiConfig($pearDB);
    $WikiURL = $conf['kb_wiki_url'];

    $currentPage = 'serviceTemplates';
    require_once $modules_path . 'search.php';

    // Init Status Template
    $status = [
        0 => "<font color='orange'> " . _('No wiki page defined') . ' </font>',
        1 => "<font color='green'> " . _('Wiki page defined') . ' </font>',
    ];

    $proc = new procedures($pearDB);
    $proc->fetchProcedures();

    // Get Services Template Informations
    $query = "
        SELECT SQL_CALC_FOUND_ROWS service_description, service_id
            FROM service
            WHERE service_register = '0'
            AND service_locked = '0' ";
    if (! empty($postServiceTemplate)) {
        $query .= ' AND service_description LIKE :postServiceTemplate ';
    }
    $query .= 'ORDER BY ' . $orderBy . ' ' . $order . ' LIMIT :offset, :limit';

    $statement = $pearDB->prepare($query);
    if (! empty($postServiceTemplate)) {
        $statement->bindValue(':postServiceTemplate', '%' . $postServiceTemplate . '%', PDO::PARAM_STR);
    }
    $statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    $rows = $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

    $selection = [];
    while ($data = $statement->fetch(PDO::FETCH_ASSOC)) {
        $data['service_description'] = str_replace('#S#', '/', $data['service_description']);
        $data['service_description'] = str_replace('#BS#', '\\', $data['service_description']);
        $selection[$data['service_description']] = $data['service_id'];
    }
    $statement->closeCursor();
    unset($data);

    // Create Diff
    $tpl->assign('host_name', _('Services Templates'));

    $diff = [];
    $templateHostArray = [];
    foreach ($selection as $key => $value) {
        $tplStr = '';
        $tplArr = $proc->getMyServiceTemplateModels($value);
        $diff[$key] = $proc->serviceTemplateHasProcedure($key, $tplArr) == true ? 1 : 0;

        if (! empty($templatesHasNoProcedure)) {
            if (
                $diff[$key] == 1
                || $proc->serviceTemplateHasProcedure($key, $tplArr, PROCEDURE_INHERITANCE_MODE) == true
            ) {
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
                        . '/index.php?title=Service-Template_:_' . $value1 . "' target='_blank'>" . $value1 . '</a>';
                    $firstTpl = 0;
                } else {
                    $tplStr .= "&nbsp;|&nbsp;<a href='" . $WikiURL
                        . '/index.php?title=Service-Template_:_' . $value1 . "' target='_blank'>" . $value1 . '</a>';
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
    $tpl->assign('selection', 3);

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
    $tpl->assign('defaultOrderby', 'service_description');

    // Apply a template definition

    $tpl->display($modules_path . 'templates/display.ihtml');
} catch (Exception $e) {
    $tpl->assign('errorMsg', $e->getMessage());
    $tpl->display($modules_path . 'templates/NoWiki.tpl');
}
