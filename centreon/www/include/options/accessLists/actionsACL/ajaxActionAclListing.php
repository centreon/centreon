<?php

require_once realpath(__DIR__ . '/../../..') . '/common/listing/AjaxListingHelper.php';

$helper   = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();
$pearDB   = $helper->getDb();
$params   = $helper->getParams();

$search = $params['search'];
$num    = $params['num'];
$limit  = $params['limit'];

$searchCond = '';
$searchParams = [];
if ($search !== '') {
    $searchCond = 'WHERE (acl_action_name LIKE :search OR acl_action_description LIKE :search) ';
    $searchParams[':search'] = '%' . $search . '%';
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS acl_action_id, acl_action_name, acl_action_description, acl_action_activate'
    . ' FROM acl_actions ' . $searchCond
    . ' ORDER BY acl_action_name LIMIT :offset, :limit'
);
foreach ($searchParams as $key => $val) {
    $statement->bindValue($key, $val, PDO::PARAM_STR);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$rows = [];
while ($acl = $statement->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = [
        'id'       => (int) $acl['acl_action_id'],
        'name'     => $acl['acl_action_name'],
        'desc'     => $acl['acl_action_description'],
        'activate' => (int) $acl['acl_action_activate'],
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
