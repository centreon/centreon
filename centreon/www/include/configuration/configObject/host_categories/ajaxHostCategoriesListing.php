<?php

require_once realpath(__DIR__ . '/../../..') . '/common/listing/AjaxListingHelper.php';

$helper   = AjaxListingHelper::boot();
$centreon = $helper->requireCentreon();
$pearDB   = $helper->getDb();
$params   = $helper->getParams();

$search = $params['search'];
$num    = $params['num'];
$limit  = $params['limit'];

// ACL filtering
$aclCond = '';
if (! $helper->isAdmin()) {
    $acl = $helper->getAcl();
    $hcString = $acl->getHostCategoriesString();
    if ($hcString !== "''" && $hcString !== '') {
        $clause = $search !== '' ? 'AND' : 'WHERE';
        $aclCond = " {$clause} hc.hc_id IN ({$hcString}) ";
    } elseif (! $helper->isAdmin()) {
        $helper->jsonResponse([], 0, 0, $limit);
    }
}

$searchCond = '';
$searchParams = [];
if ($search !== '') {
    $searchCond = 'WHERE (hc.hc_name LIKE :search OR hc.hc_alias LIKE :search) ';
    $searchParams[':search'] = '%' . $search . '%';
}

$statement = $pearDB->prepare(
    'SELECT SQL_CALC_FOUND_ROWS hc.hc_id, hc.hc_name, hc.hc_alias, hc.hc_activate, hc.level,'
    . ' (SELECT COUNT(*) FROM hostcategories_relation hr INNER JOIN host h ON h.host_id = hr.host_host_id WHERE hr.hostcategories_hc_id = hc.hc_id AND h.host_activate = "1") AS enabled_hosts,'
    . ' (SELECT COUNT(*) FROM hostcategories_relation hr INNER JOIN host h ON h.host_id = hr.host_host_id WHERE hr.hostcategories_hc_id = hc.hc_id AND h.host_activate = "0") AS disabled_hosts'
    . ' FROM hostcategories hc ' . $searchCond . $aclCond
    . ' ORDER BY hc.hc_name LIMIT :offset, :limit'
);
foreach ($searchParams as $key => $val) {
    $statement->bindValue($key, $val, PDO::PARAM_STR);
}
$statement->bindValue(':offset', $num * $limit, PDO::PARAM_INT);
$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
$statement->execute();

$total = (int) $pearDB->query('SELECT FOUND_ROWS()')->fetchColumn();

$rows = [];
while ($hc = $statement->fetch(PDO::FETCH_ASSOC)) {
    $rows[] = [
        'id'             => (int) $hc['hc_id'],
        'name'           => $hc['hc_name'],
        'alias'          => $hc['hc_alias'],
        'enabled_hosts'  => (int) $hc['enabled_hosts'],
        'disabled_hosts' => (int) $hc['disabled_hosts'],
        'level'          => $hc['level'] ? (int) $hc['level'] : null,
        'activate'       => (int) $hc['hc_activate'],
    ];
}

$helper->jsonResponse($rows, $total, $num, $limit);
