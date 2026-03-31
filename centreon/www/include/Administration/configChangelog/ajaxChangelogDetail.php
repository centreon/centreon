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

require_once realpath(__DIR__ . '/../..') . '/common/listing/AjaxListingHelper.php';

$helper   = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();
$pearDB   = $helper->getDb();

// ACL
if (! $helper->isAdmin()) {
    $acl = $helper->getAcl();
    if (! $acl || $acl->page(508) === 0) {
        AjaxListingHelper::jsonError('Access denied', 403);
    }
}

$actionLogId = filter_var($_GET['action_log_id'] ?? null, FILTER_VALIDATE_INT);
if (! $actionLogId) {
    AjaxListingHelper::jsonError('Invalid parameters', 400);
}

$pearDBO = new CentreonDB('centstorage');

// Get the action info
$stmtAction = $pearDBO->prepare(
    'SELECT action_log_id, object_id, object_type, action_type, action_log_date FROM log_action WHERE action_log_id = :id'
);
$stmtAction->bindValue(':id', $actionLogId, PDO::PARAM_INT);
$stmtAction->execute();
$action = $stmtAction->fetch(PDO::FETCH_ASSOC);

if (! $action) {
    AjaxListingHelper::jsonError('Not found', 404);
}

// Get fields for this action
$stmtFields = $pearDBO->prepare(
    'SELECT field_name, field_value FROM log_action_modification WHERE action_log_id = :id ORDER BY field_name'
);
$stmtFields->bindValue(':id', $actionLogId, PDO::PARAM_INT);
$stmtFields->execute();

$currentFields = [];
while ($f = $stmtFields->fetch(PDO::FETCH_ASSOC)) {
    $currentFields[$f['field_name']] = $f['field_value'];
}

// Find the previous action for the same object to compute "before" values
$stmtPrev = $pearDBO->prepare(
    'SELECT action_log_id FROM log_action '
    . 'WHERE object_id = :obj_id AND object_type = :obj_type AND action_log_date < :date '
    . 'ORDER BY action_log_date DESC LIMIT 1'
);
$stmtPrev->bindValue(':obj_id', $action['object_id'], PDO::PARAM_INT);
$stmtPrev->bindValue(':obj_type', $action['object_type'], PDO::PARAM_STR);
$stmtPrev->bindValue(':date', $action['action_log_date'], PDO::PARAM_INT);
$stmtPrev->execute();
$prevId = $stmtPrev->fetchColumn();

$prevFields = [];
if ($prevId) {
    $stmtPrevFields = $pearDBO->prepare(
        'SELECT field_name, field_value FROM log_action_modification WHERE action_log_id = :id'
    );
    $stmtPrevFields->bindValue(':id', (int) $prevId, PDO::PARAM_INT);
    $stmtPrevFields->execute();
    while ($f = $stmtPrevFields->fetch(PDO::FETCH_ASSOC)) {
        $prevFields[$f['field_name']] = $f['field_value'];
    }
}

// Mask password fields
$passwordFields = ['contact_passwd', 'contact_passwd2'];
$macroPasswordRef = [];
if (isset($currentFields['refMacroPassword'])) {
    $macroPasswordRef = explode(',', $currentFields['refMacroPassword']);
}

// Build diff
$diff = [];
// Skip internal/meta fields
$skipFields = ['refMacroPassword'];

foreach ($currentFields as $fieldName => $afterValue) {
    if (in_array($fieldName, $skipFields, true)) {
        continue;
    }

    // Mask passwords
    if (in_array($fieldName, $passwordFields, true)) {
        $afterValue = '******';
        $beforeValue = isset($prevFields[$fieldName]) ? '******' : '';
    } else {
        $beforeValue = $prevFields[$fieldName] ?? '';
    }

    // Mask macro passwords
    if ($fieldName === 'macroValue' && ! empty($macroPasswordRef)) {
        $afterParts = explode(',', $afterValue);
        $beforeParts = $beforeValue !== '' ? explode(',', $beforeValue) : [];
        foreach ($macroPasswordRef as $idx) {
            if (isset($afterParts[(int) $idx])) {
                $afterParts[(int) $idx] = '******';
            }
            if (isset($beforeParts[(int) $idx])) {
                $beforeParts[(int) $idx] = '******';
            }
        }
        $afterValue = implode(',', $afterParts);
        $beforeValue = ! empty($beforeParts) ? implode(',', $beforeParts) : '';
    }

    // Only include if there's an actual change (or it's a create)
    if ($beforeValue !== $afterValue || $action['action_type'] === 'a') {
        $diff[] = [
            'field'  => $fieldName,
            'before' => $beforeValue,
            'after'  => $afterValue,
        ];
    }
}

echo json_encode([
    'action_type' => $action['action_type'],
    'diff'        => $diff,
]);
exit;
