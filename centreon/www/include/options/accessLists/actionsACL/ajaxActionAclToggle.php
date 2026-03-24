<?php

require_once realpath(__DIR__ . '/../../..') . '/common/listing/AjaxListingHelper.php';

$helper   = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();
$pearDB   = $helper->getDb();

$aclId  = filter_var($_POST['sg_id'] ?? null, FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? null;

if (! $aclId || ! in_array($action, ['s', 'u'], true)) {
    AjaxListingHelper::jsonError('Invalid parameters', 400);
}

$newToken = $helper->validateCsrfToken();

$checkStmt = $pearDB->prepare('SELECT acl_action_id FROM acl_actions WHERE acl_action_id = :id');
$checkStmt->bindValue(':id', $aclId, PDO::PARAM_INT);
$checkStmt->execute();
if (! $checkStmt->fetch()) {
    AjaxListingHelper::jsonError('Action ACL not found', 404);
}

$activate = ($action === 's') ? '1' : '0';
$statement = $pearDB->prepare("UPDATE acl_actions SET acl_action_activate = :activate WHERE acl_action_id = :id");
$statement->bindValue(':activate', $activate, PDO::PARAM_STR);
$statement->bindValue(':id', $aclId, PDO::PARAM_INT);
$statement->execute();

echo json_encode(['success' => true, 'centreon_token' => $newToken]);

exit;
