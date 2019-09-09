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

header("Content-type: text/xml");
header("Content-Disposition: attachment; filename=TicketLogs.xml");
header("Cache-Control: cache, must-revalidate");
header("Pragma: public");

//$fp = fopen('/tmp/debug.txt', 'a+');
//fwrite($fp, print_r($_SESSION['OT_form_logs'], true));

try {
    $tickets = $ticket_log->getLog($_SESSION['OT_form_logs'], $centreon_bg, null, null, true);
    //fwrite($fp, print_r($tickets, true));

    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<root>';

    echo '<info>';
    echo '<start>' . $centreon_bg->GMT->getDate('m/d/Y (H:i:s)', intval($tickets['start'])) . "</start><end>" . $centreon_bg->GMT->getDate('m/d/Y (H:i:s)', intval($tickets['end'])) . "</end>";
    echo "</info>";
    echo '<data>';

    foreach ($tickets['tickets'] as $ticket) {
        echo '<line>';
        echo '<day>' . $centreon_bg->GMT->getDate("Y/m/d", $ticket['timestamp']) . "</day><time>" . $centreon_bg->GMT->getDate("H:i:s", $ticket['timestamp']) . "</time><host_name>" . $ticket['host_name'] . "</host_name><service_description>" . $ticket['service_description'] . "</service_description><ticket_id>" . $ticket['ticket_id'] . "</ticket_id><user>" . $ticket['user'] . "</user><subject>" . $ticket['subject'] . "</subject>";
        echo '</line>';
    }
    echo '</data>';
    echo '</root>';
} catch (Exception $e) {

}

exit(1);

?>