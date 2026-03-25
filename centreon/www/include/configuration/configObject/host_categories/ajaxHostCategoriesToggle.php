<?php

require_once realpath(__DIR__ . '/../../..') . '/common/listing/AjaxListingHelper.php';

$helper   = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();
$pearDB   = $helper->getDb();

$hcId   = filter_var($_POST['sg_id'] ?? null, FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? null;

if (! $hcId || ! in_array($action, ['s', 'u'], true)) {
    AjaxListingHelper::jsonError('Invalid parameters', 400);
}

$newToken = $helper->validateCsrfToken();

$checkStmt = $pearDB->prepare('SELECT hc_id FROM hostcategories WHERE hc_id = :id');
$checkStmt->bindValue(':id', $hcId, PDO::PARAM_INT);
$checkStmt->execute();
if (! $checkStmt->fetch()) {
    AjaxListingHelper::jsonError('Host category not found', 404);
}

$activate = ($action === 's') ? '1' : '0';
$statement = $pearDB->prepare("UPDATE hostcategories SET hc_activate = :activate WHERE hc_id = :id");
$statement->bindValue(':activate', $activate, PDO::PARAM_STR);
$statement->bindValue(':id', $hcId, PDO::PARAM_INT);
$statement->execute();

echo json_encode(['success' => true, 'centreon_token' => $newToken]);

exit;
