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

header("Content-Type: application/csv-tab-delimited-table");
header("Content-disposition: filename=TicketLogs.csv");
header("Cache-Control: cache, must-revalidate");
header("Pragma: public");

//$fp = fopen('/tmp/debug.txt', 'a+');
//fwrite($fp, print_r($_SESSION['OT_form_logs'], true));

try {
    $tickets = $ticket_log->getLog($_SESSION['OT_form_logs'], $centreon_bg, null, null, true);
    //fwrite($fp, print_r($tickets, true));

    echo _("Begin date")."; "._("End date").";\n";
    echo $centreon_bg->GMT->getDate('m/d/Y (H:i:s)', intval($tickets['start'])) . ";" . $centreon_bg->GMT->getDate('m/d/Y (H:i:s)', intval($tickets['end'])) . "\n";
    echo "\n";

    echo _("Day") . ";" . _("Time") . ";" . _("Host") . ";" . _("Service") . ";" . _("Ticket ID") . ";" . _("User") . ";" . _("Subject")."\n";
    foreach ($tickets['tickets'] as $ticket) {
        echo $centreon_bg->GMT->getDate("Y/m/d", $ticket['timestamp']) . ";" . $centreon_bg->GMT->getDate("H:i:s", $ticket['timestamp']) . ";" . $ticket['host_name'] . ";" . $ticket['service_description'] . ";" . $ticket['ticket_id'] . ";" . $ticket['user'] . ";" . $ticket['subject'] . "\n";
    }
} catch (Exception $e) {

}

exit(1);

?>