<?php
/*
 * Copyright 2005-2016 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */
$stateType = 'service';
require_once realpath(__DIR__ . "/initXmlFeed.php");

if (isset($_SESSION['centreon'])) {
    $centreon = $_SESSION['centreon'];
} else {
    exit;
}

$color = array_filter($_GET['color'] ?? [], function ($oneColor) {
    return filter_var($oneColor, FILTER_VALIDATE_REGEXP, [
        'options' => [
            'regexp' => "/^#[[:xdigit:]]{6}$/"
        ]
    ]);
});
if (empty($color) || count($_GET['color']) !== count($color)) {
    $buffer->writeElement('error', 'Bad color format');
    $buffer->endElement();
    header('Content-Type: text/xml');
    $buffer->output();
    exit;
}

if (
    ($id = filter_var($_GET['id'] ?? false, FILTER_VALIDATE_INT)) !== false
    && ($host_id = filter_var($_GET['host_id'] ?? false, FILTER_VALIDATE_INT)) !== false
    && ($startDate = filter_var($_GET['startDate'] ?? false, FILTER_VALIDATE_INT)) !== false
    && ($endDate = filter_var($_GET['endDate'] ?? false, FILTER_VALIDATE_INT)) !== false
) {
    /* Get ACL if user is not admin */
    $isAdmin = $centreon->user->admin;
    $accessService = true;
    if (!$isAdmin) {
        $userId = $centreon->user->user_id;
        $acl = new CentreonACL($userId, $isAdmin);
        if (!$acl->checkService($id)) {
            $accessService = false;
        }
    }

    if ($accessService) {
        /* Use "like" instead of "=" to avoid mysql bug on partitioned tables */
        $query = 'SELECT *
            FROM `log_archive_service`
            WHERE host_id LIKE :host_id
            AND service_id LIKE :service_id
            AND date_start >= :start_date
            AND date_end <= :end_date
            ORDER BY date_start DESC';
        $stmt = $pearDBO->prepare($query);
        $stmt->bindValue(':host_id', $host_id, PDO::PARAM_INT);
        $stmt->bindValue(':service_id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':start_date', $startDate, PDO::PARAM_INT);
        $stmt->bindValue(':end_date', $endDate, PDO::PARAM_INT);
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            fillBuffer($statesTab, $row, $color);
        }
    } else {
        $buffer->writeElement("error", "Cannot access to host information");
    }
} else {
    $buffer->writeElement('error', 'Bad id format');
}
$buffer->endElement();
header('Content-Type: text/xml');
$buffer->output();
