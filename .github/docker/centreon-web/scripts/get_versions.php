<?php
require_once __DIR__ . '/../class/centreonDB.class.php';

$db = new CentreonDB();
$res = $db->query("SELECT `value` FROM `informations` WHERE `key` = 'version'");
$row = $res->fetchRow();
$current = $row['value'];

$next = '';
$phpDir = __DIR__ . '/php';
if ($handle = opendir($phpDir)) {
    while (false !== ($file = readdir($handle))) {
        if (preg_match('/Update-([0-9.]+)\.php/', $file, $matches)) {
            if (version_compare($current, $matches[1]) < 0 &&
                (empty($next) || version_compare($next, $matches[1]) < 0)) {
                $next = $matches[1];
            }
        }
    }
    closedir($handle);
}

echo json_encode([
    'current' => $current,
    'next' => $next
], JSON_PRETTY_PRINT);
