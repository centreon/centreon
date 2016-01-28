<?php
/**
 * Copyright 2005-2011 MERETHIS
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
 * As a special exception, the copyright holders of this program give MERETHIS
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of MERETHIS choice, provided that
 * MERETHIS also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

require_once "../../require.php";
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonUtils.class.php';
require_once $centreon_path . "www/class/centreonXMLBGRequest.class.php";
require_once $centreon_path . 'www/modules/centreon-open-tickets/class/rule.php';
require_once $centreon_path . "GPL_LIB/Smarty/libs/Smarty.class.php";

session_start();
$centreon_bg = new CentreonXMLBGRequest(session_id(), 1, 1, 0, 1);

?>

<script type="text/javascript" src="../../../include/common/javascript/jquery/jquery.js"></script>
<script type="text/javascript" src="../../../include/common/javascript/jquery/jquery-ui.js"></script>
<script type="text/javascript" src="../../../include/common/javascript/widgetUtils.js"></script>
<script type="text/javascript" src="../../../modules/centreon-open-tickets/lib/jquery.serialize-object.min.js"></script>
<script type="text/javascript" src="../../../modules/centreon-open-tickets/lib/commonFunc.js"></script>

<?php

function format_popup() {
    global $cmd, $widgetId, $rule, $preferences, $centreon, $centreon_path;
    
    if ($cmd == 3) {
        $title = _("Open Service Ticket");
    } else {
        $title = _("Open Host Ticket");
    }
    
    $result = $rule->getFormatPopupProvider($preferences['rule'], 
                                            array('title' => $title,
                                                  'user' => $centreon->user->alias,
                                                 )
                                            );
    
    $path = $centreon_path . "www/widgets/open-tickets/src/";
    $template = new Smarty();
    $template = initSmartyTplForPopup($path . 'templates/', $template, "./", $centreon_path);
    
    $provider_infos = $rule->getAliasAndProviderId($preferences['rule']);
    
    $template->assign('provider_id', $provider_infos['provider_id']);
    $template->assign('rule_id', $preferences['rule']);
    $template->assign('widgetId', $widgetId);
    $template->assign('title', $title);
    $template->assign('cmd', $cmd);
    $template->assign('selection', $_REQUEST['selection']);
    $template->assign('continue', (!is_null($result) && isset($result['format_popup'])) ? 0 : 1);

    $template->assign('formatPopupProvider', (!is_null($result) && isset($result['format_popup'])) ? $result['format_popup'] : '');
    
    $template->assign('submitLabel', _("Open"));
     
    $template->display('formatpopup.ihtml');    
}

try {
    if (!isset($_SESSION['centreon']) || !isset($_REQUEST['cmd']) || !isset($_REQUEST['selection'])) {
        throw new Exception('Missing data');
    }
    $db = new CentreonDB();
    if (CentreonSession::checkSession(session_id(), $db) == 0) {
        throw new Exception('Invalid session');
    }
    $centreon = $_SESSION['centreon'];
    $oreon = $centreon;
    $cmd = $_REQUEST['cmd'];
    
    $widgetId = $_REQUEST['widgetId'];
    $selections = explode(",", $_REQUEST['selection']);
    
    $widgetObj = new CentreonWidget($centreon, $db);
    $preferences = $widgetObj->getWidgetPreferences($widgetId);
        
    $rule = new Centreon_OpenTickets_Rule($db);
    
    if ($cmd == 3 || $cmd == 4) {
        format_popup();
    }
} catch (Exception $e) {
    echo $e->getMessage() . "<br/>";
}
?>
