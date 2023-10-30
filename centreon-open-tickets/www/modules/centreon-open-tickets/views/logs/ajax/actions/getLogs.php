<?php
/*
 * Copyright 2016-2019 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets
 * the needs in IT infrastructure and application monitoring for
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

function set_pagination($tpl, $pagination, $current_page, $rows) {
    $tpl->assign("pagination", $pagination);
    $tpl->assign("current_page", $current_page);
    $num_page = (int)(($rows / $pagination) + 1);
    $tpl->assign("num_page", $num_page);

    $total = 10;
    $bottom = $current_page - 1;
    $top = $current_page + 1;

    $bottom_display = array();
    $top_display = array();
    $arrow_first_display = 1;
    $arrow_last_display = 1;

    for (; $bottom > 0 && count($bottom_display) < 6; $bottom--) {
        $bottom_display[] = $bottom;
        $total--;
    }
    sort($bottom_display, SORT_NUMERIC);
    if ($bottom <= 0) {
        $arrow_first_display = 0;
    }

    for (; $top <= $num_page && $total >= 0; $top++) {
        $top_display[] = $top;
        $total--;
    }
    sort($top_display, SORT_NUMERIC);
    if ($top > (($rows / $pagination) + 1)) {
        $arrow_last_display = 0;
    }

    $tpl->assign("bottom_display", $bottom_display);
    $tpl->assign("top_display", $top_display);
    $tpl->assign("arrow_first_display", $arrow_first_display);
    $tpl->assign("arrow_last_display", $arrow_last_display);
}

$resultat = array(
    "code" => 0,
    "msg" => 'ok',
    "data" => null,
    "pagination" => null
);

//$fp = fopen('/tmp/debug.txt', 'a+');
//fwrite($fp, print_r($get_information, true));

$_SESSION['OT_form_logs'] = $get_information['form'];

try {
    $tickets = $ticket_log->getLog($get_information['form'], $centreon_bg, $get_information['pagination'], $get_information['current_page']);
    //fwrite($fp, print_r($tickets, true));

    $tpl = new Smarty();
    $tpl = initSmartyTplForPopup($centreon_open_tickets_path, $tpl, 'views/logs/templates', $centreon_path);

    $tpl->assign("tickets", $tickets['tickets']);
    $resultat['data'] = $tpl->fetch('data.ihtml');

    // Get Pagination
    set_pagination($tpl, $get_information['pagination'], $get_information['current_page'], $tickets['rows']);
    $resultat['pagination'] = $tpl->fetch('pagination.ihtml');
} catch (Exception $e) {
    $resultat['code'] = 1;
    $resultat['msg'] = $e->getMessage();
}

?>