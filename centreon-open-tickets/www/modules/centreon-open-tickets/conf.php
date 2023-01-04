<?php

/*
 * Copyright 2016-2021 Centreon (http://www.centreon.com/)
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

$module_conf['centreon-open-tickets']["rname"] = "Centreon Open Tickets";
$module_conf['centreon-open-tickets']["name"] = "centreon-open-tickets";
$module_conf['centreon-open-tickets']["mod_release"] = "22.10.1";
$module_conf['centreon-open-tickets']["infos"] = "Centreon Open Tickets is a community module developed to " .
    "create tickets to your favorite ITSM tools using API.

Once done provider configuration, the module allows for an operator to create tickets for hosts and services " .
    "in a non-ok state using a dedicated widget. Indeed, a button associated with each host or service allows " .
    "you to connect to the API and create the ticket while offering the possibility to acknowledge at the same " .
    "time the object.

Regarding the widget configuration, it is possible to see the created tickets by presenting tickets ID and " .
    "date of creation of these.
";
$module_conf['centreon-open-tickets']["is_removeable"] = "1";
$module_conf['centreon-open-tickets']["author"] = "Centreon";
$module_conf['centreon-open-tickets']["stability"] = "stable";
$module_conf['centreon-open-tickets']["last_update"] = "2022-01-04";
$module_conf['centreon-open-tickets']["release_note"] =
    "https://docs.centreon.com/22.10/en/releases/centreon-os-extensions.html";
$module_conf['centreon-open-tickets']["images"] = [
    'images/image1.png',
    'images/image2.png',
    'images/image3.png'
];
