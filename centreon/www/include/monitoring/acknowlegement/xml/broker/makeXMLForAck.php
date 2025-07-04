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

require_once realpath(__DIR__ . '/../../../../../../config/centreon.config.php');
include_once _CENTREON_PATH_ . 'www/class/centreonDuration.class.php';
include_once _CENTREON_PATH_ . 'www/class/centreonGMT.class.php';
include_once _CENTREON_PATH_ . 'www/class/centreonXML.class.php';
include_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
include_once _CENTREON_PATH_ . 'www/class/centreonSession.class.php';
include_once _CENTREON_PATH_ . 'www/class/centreon.class.php';
include_once _CENTREON_PATH_ . 'www/class/centreonLang.class.php';
include_once _CENTREON_PATH_ . 'www/include/common/common-Func.php';

session_start();
$oreon = $_SESSION['centreon'];

$db = new CentreonDB();
$dbb = new CentreonDB('centstorage');

$centreonLang = new CentreonLang(_CENTREON_PATH_, $oreon);
$centreonLang->bindLang();
$sid = session_id();
if (isset($sid)) {
    $res = $db->prepare('SELECT * FROM session WHERE session_id = :sid');
    $res->bindValue(':sid', $sid, PDO::PARAM_STR);
    $res->execute();
    if (! $session = $res->fetch()) {
        get_error('bad session id');
    }
} else {
    get_error('need session id !');
}

// sanitize host and service id from request;
$hostId = filter_var($_GET['hid'] ?? false, FILTER_VALIDATE_INT);
$svcId = filter_var($_GET['svc_id'] ?? false, FILTER_VALIDATE_INT);

// check if a mandatory valid hostId is given
if (false === $hostId) {
    get_error('bad host Id');
}

// Init GMT class
$centreonGMT = new CentreonGMT($db);
$centreonGMT->getMyGMTFromSession($sid);

// Start Buffer
$xml = new CentreonXML();
$xml->startElement('response');
$xml->startElement('label');
$xml->writeElement('author', _('Author'));
$xml->writeElement('entrytime', _('Entry Time'));
$xml->writeElement('persistent', _('Persistent'));
$xml->writeElement('sticky', _('Sticky'));
$xml->writeElement('comment', _('Comment'));
$xml->endElement();

// Retrieve info
if (false === $svcId) {
    $res = $dbb->prepare(
        'SELECT author, entry_time, comment_data, persistent_comment, sticky
        FROM acknowledgements
        WHERE host_id = :hostId
        AND service_id = 0
        ORDER BY entry_time DESC
        LIMIT 1'
    );
    $res->bindValue(':hostId', $hostId, PDO::PARAM_INT);
    $res->execute();
} else {
    $res = $dbb->prepare(
        'SELECT author, entry_time, comment_data, persistent_comment, sticky
        FROM acknowledgements
        WHERE host_id = :hostId
        AND service_id = :svcId
        ORDER BY entry_time DESC
        LIMIT 1'
    );
    $res->bindValue(':hostId', $hostId, PDO::PARAM_INT);
    $res->bindValue(':svcId', $svcId, PDO::PARAM_INT);
    $res->execute();
}

$rowClass = 'list_one';
while ($row = $res->fetch()) {
    $row['comment_data'] = strip_tags($row['comment_data']);
    $xml->startElement('ack');
    $xml->writeAttribute('class', $rowClass);
    $xml->writeElement('author', $row['author']);
    $xml->writeElement('entrytime', $row['entry_time']);
    $xml->writeElement('comment', $row['comment_data']);
    $xml->writeElement('persistent', $row['persistent_comment'] ? _('Yes') : _('No'));
    $xml->writeElement('sticky', $row['sticky'] ? _('Yes') : _('No'));
    $xml->endElement();
    $rowClass = $rowClass === 'list_one' ? 'list_two' : 'list_one';
}

// End buffer
$xml->endElement();
header('Content-type: text/xml; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Print Buffer
$xml->output();
