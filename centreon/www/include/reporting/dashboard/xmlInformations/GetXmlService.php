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

$stateType = 'service';
require_once realpath(__DIR__ . '/initXmlFeed.php');

if (isset($_SESSION['centreon'])) {
    $centreon = $_SESSION['centreon'];
} else {
    exit;
}

$color = array_filter($_GET['color'] ?? [], function ($oneColor) {
    return filter_var($oneColor, FILTER_VALIDATE_REGEXP, [
        'options' => [
            'regexp' => '/^#[[:xdigit:]]{6}$/',
        ],
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
    // Get ACL if user is not admin
    $isAdmin = $centreon->user->admin;
    $accessService = true;
    if (! $isAdmin) {
        $userId = $centreon->user->user_id;
        $acl = new CentreonACL($userId, $isAdmin);
        if (! $acl->checkService($id)) {
            $accessService = false;
        }
    }

    if ($accessService) {
        // Use "like" instead of "=" to avoid mysql bug on partitioned tables
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
        $buffer->writeElement('error', 'Cannot access to host information');
    }
} else {
    $buffer->writeElement('error', 'Bad id format');
}
$buffer->endElement();
header('Content-Type: text/xml');
$buffer->output();
