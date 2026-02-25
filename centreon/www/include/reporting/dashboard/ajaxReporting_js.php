<?php
/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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
require_once realpath(__DIR__ . '/../../../../config/centreon.config.php');

$arg = $type == 'Service' ? 'id=' . $serviceId . '&host_id=' . $hostId : 'id=' . $id;

$arg .= '&color[UP]=#' . $colors['up'] . '&color[UNDETERMINED]=#' . $colors['undetermined']
        . '&color[DOWN]=#' . $colors['down'] . '&color[UNREACHABLE]=#' . $colors['unreachable']
        . '&color[OK]=#' . $colors['ok'] . '&color[WARNING]=#' . $colors['warning']
        . '&color[CRITICAL]=#' . $colors['critical'] . '&color[UNKNOWN]=#' . $colors['unknown']
        . '&startDate=' . $startDate . '&endDate=' . $endDate;
$arg = str_replace('#', '%23', $arg);
$url = './include/reporting/dashboard/xmlInformations/GetXml' . $type . '.php?' . $arg;

?>
<script type="text/javascript">

var tl;

function initTimeline() {
    var eventSource = new Timeline.DefaultEventSource();
    var bandInfos = [
    Timeline.createBandInfo({
            eventSource:    eventSource,
            width:          "70%",
            intervalUnit:   Timeline.DateTime.DAY,
            intervalPixels: 300
        }),
        Timeline.createBandInfo({
            showEventText:  false,
            eventSource:    eventSource,
            width:          "30%",
            intervalUnit:   Timeline.DateTime.MONTH,
            intervalPixels: 300
        })
    ];

    bandInfos[1].syncWith = 0;
    bandInfos[1].highlight = true;
    bandInfos[1].eventPainter.setLayout(bandInfos[0].eventPainter.getLayout());

    tl = Timeline.create(document.getElementById("my-timeline"), bandInfos);

    Timeline.loadXML('<?php echo $url; ?>', function(xml, url) { eventSource.loadXML(xml, url); });
}
</script>
