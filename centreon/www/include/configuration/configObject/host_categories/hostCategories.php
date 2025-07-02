<?php

if (! isset($centreon)) {
    exit();
}

$hG = $_GET['hc_id'] ?? null;
$hP = $_POST['hc_id'] ?? null;
$hc_id = $hG ?: $hP;

$cG = $_GET['select'] ?? null;
$cP = $_POST['select'] ?? null;
$select = $cG ?: $cP;

$cG = $_GET['dupNbr'] ?? null;
$cP = $_POST['dupNbr'] ?? null;
$dupNbr = $cG ?: $cP;

// Path to the configuration dir
$path = './include/configuration/configObject/host_categories/';

// PHP functions
require_once $path . 'DB-Func.php';
require_once './include/common/common-Func.php';

use Core\Common\Domain\Exception\RepositoryException;

// Set the real page
if (isset($ret) && is_array($ret) && $ret['topology_page'] != '' && $p != $ret['topology_page']) {
    $p = $ret['topology_page'];
}

$acl = $centreon->user->access;
$dbmon = new CentreonDB('centstorage');
$aclDbName = $acl->getNameDBAcl();
$hcString = $acl->getHostCategoriesString();
$hoststring = $acl->getHostsString('ID', $dbmon);
try {
    switch ($o) {
        case 'a':
            require_once $path . 'formHostCategories.php';
            break;
        case 'w':
            require_once $path . 'formHostCategories.php';
            break;
        case 'c':
            require_once $path . 'formHostCategories.php';
            break;
        case 's':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                enableHostCategoriesInDB($hc_id);
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listHostCategories.php';
            break;
        case 'ms':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                enableHostCategoriesInDB(null, $select ?? []);
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listHostCategories.php';
            break;
        case 'u':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                disableHostCategoriesInDB($hc_id);
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listHostCategories.php';
            break;
        case 'mu':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                disableHostCategoriesInDB(null, $select ?? []);
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listHostCategories.php';
            break;
        case 'm':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                multipleHostCategoriesInDB($select ?? [], $dupNbr);
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listHostCategories.php';
            break;
        case 'd':
            purgeOutdatedCSRFTokens();
            if (isCSRFTokenValid()) {
                purgeCSRFToken();
                deleteHostCategoriesInDB($select ?? []);
            } else {
                unvalidFormMessage();
            }
            require_once $path . 'listHostCategories.php';
            break;
        default:
            require_once $path . 'listHostCategories.php';
            break;
    }
} catch (RepositoryException $exception) {
    CentreonLog::create()->error(
        CentreonLog::TYPE_SQL,
        'Error while processing host categories: ' . $exception->getMessage(),
        exception: $exception
    );
    $msg = new CentreonMsg();
    $msg->setImage('./img/icons/warning.png');
    $msg->setTextStyle('bold');
    $msg->setText('Error while processing host categories');
}
