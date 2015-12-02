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

require_once "../require.php";
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';

session_start();

if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
  print "DDD";
    exit;
}
$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];

try {
    global $pearDB;

    $db = new CentreonDB();
    $db2 = new CentreonDB("centstorage");
    $pearDB = $db;

    if ($centreon->user->admin == 0) {
        $access = new CentreonACL($centreon->user->get_id());
        $grouplist = $access->getAccessGroups();
        $grouplistStr = $access->getAccessGroupsString();
    }

    $widgetObj = new CentreonWidget($centreon, $db);
    $preferences = $widgetObj->getWidgetPreferences($widgetId);
    $autoRefresh = 0;
    if (isset($preferences['refresh_interval'])) {
        $autoRefresh = $preferences['refresh_interval'];
    }
} catch (Exception $e) {
    echo $e->getMessage() . "<br/>";
    exit;
}
?>
<html>
    <head>
    	<title>Top10-cpu</title>
        <link href="../../Theme/Centreon-2/style.css" type="text/css" />
        <link href="src/top10_memory.css" rel="styleheet" type="text/css"/>
    <link href="<?php echo '../../Themes/Centreon-2/Color/blue_css.php';?>" rel="stylesheet" type="text/css"/>
        <script type="text/javascript" src="../../include/common/javascript/jquery/jquery.js"></script>
    	<script type="text/javascript" src="../../include/common/javascript/jquery/jquery-ui.js"></script>
        <script type="text/javascript" src="../../include/common/javascript/widgetUtils.js"></script>
  <style type="text/css">
    .ListTable {font-size:11px;border-color: #BFD0E2;}                                                                                                                                                      
   </style>        
  </head>
    <body>
    <?php
    if (/* isset($preferences['']) */ /* && */ $preferences['service_description'] != '' && $preferences['metric_name'] != '' && $preferences['nb_lin'] != '') {
      print "<div id=\"top10cpu\"></div>";
    } else {
      print "<center><div class='update' style='text-align:center;width:350px;'>"._("Please select a metric_name, nb_lin, service_description")."</div></center>";
    }
?>
    </body>
<script type="text/javascript">
    var widgetId = <?php echo $widgetId; ?>;
    var autoRefresh = <?php echo $autoRefresh;?>;
    var timeout;
    
jQuery(function() {
    console.log("jQuery function");
        loadTop10();
});
    
function loadTop10() {
    jQuery.ajax("./src/data.php?widgetId="+widgetId, {
        success : function(htmlData) {
             console.log("Success load");
            jQuery("#top10cpu").html("");
            jQuery("#top10cpu").html(htmlData);
            var h = document.getElementById("top10cpu").scrollHeight + 10;
            if(h){
                console.log("h recupérée");
                parent.iResize(window.name, h);
            }else{
                parent.iResize(window.name, 200);
            }
        }
    });
    if (autoRefresh) {
        if (timeout) {
            clearTimeout(timeout);
        }
        timeout = setTimeout(loadTop10, (autoRefresh * 1000));
    }
}
</script>
</html>
