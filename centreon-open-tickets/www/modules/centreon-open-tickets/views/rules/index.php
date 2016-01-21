<?php
/*
 * CENTREON
 *
 * Source Copyright 2005-2015 CENTREON
 *
 * Unauthorized reproduction, copy and distribution
 * are not allowed.
 *
 * For more information : contact@centreon.com
 *
*/

require_once './modules/centreon-open-tickets/centreon-open-tickets.conf.php';

$db = new CentreonDBManager();
$request = new Centreon_OpenTickets_Request();
$rule = new Centreon_OpenTickets_Rule($db);

$o = $request->getParam('o');
if (!$o) {
    $o = $request->getParam('o1');
}
if (!$o) {
    $o = $request->getParam('o2');
}
$ruleId = $request->getParam('rule_id');
$select = $request->getParam('select');
$duplicateNb = $request->getParam('duplicateNb');
$p = $request->getParam('p');
$num = $request->getParam('num');
$limit = $request->getParam('limit');
$search = $request->getParam('searchRule');

require_once "HTML/QuickForm.php";
require_once 'HTML/QuickForm/advmultiselect.php';
require_once 'HTML/QuickForm/Renderer/ArraySmarty.php';

try {
    switch ($o) {
        case 'a':
            require_once 'form.php';
            break;
        case 'd':
            $rule->delete($select);
            require_once 'list.php';
            break;
        case 'c':
            require_once 'form.php';
            break;
        case 'l':
            require_once 'list.php';
            break;
        case 'dp':
            $rule->duplicate($select, $duplicateNb);
            require_once 'list.php';
            break;
        case 'e':
            $rule->enable($select);
            require_once 'list.php';
            break;
        case 'ds':
            $rule->disable($select);
            require_once 'list.php';
            break;
        default:
            require_once 'list.php';
            break;
    }
}
catch (Exception $e) {
    echo $e->getMessage() . "<br/>";
}

?>
