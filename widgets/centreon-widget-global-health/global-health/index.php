<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


require_once "../require.php";
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';

session_start();
if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit;
}
$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];

try {
    $db = new CentreonDB();
    $widgetObj = new CentreonWidget($centreon, $db);
    $preferences = $widgetObj->getWidgetPreferences($widgetId);
    $autoRefresh = 0;
    if (isset($preferences['refresh_interval'])) {
        $autoRefresh = $preferences['refresh_interval'];
    }
    $broker = "broker";
    $res = $db->query("SELECT `value` FROM `options` WHERE `key` = 'broker'");
    if ($res->numRows()) {
        $row = $res->fetchRow();
        $broker = strtolower($row['value']);
    } else {
        throw new Exception('Unknown broker module');
    }
} catch (Exception $e) {
    echo $e->getMessage() . "<br/>";
    exit;
}
?>


<html>
<head>
    	<title></title>
    	<link href="../../Themes/Centreon-2/style.css" rel="stylesheet" type="text/css"/>
    	<link href="../../Themes/Centreon-2/jquery-ui/jquery-ui.css" rel="stylesheet" type="text/css"/>
    	<link href="../../Themes/Centreon-2/jquery-ui/jquery-ui-centreon.css" rel="stylesheet" type="text/css"/>
    	<script type="text/javascript" src="../../include/common/javascript/jquery/jquery.js"></script>
    	<script type="text/javascript" src="../../include/common/javascript/jquery/jquery-ui.js"></script>
    	<script type="text/javascript" src="../../include/common/javascript/jquery/plugins/pagination/jquery.pagination.js"></script>
		<script type="text/javascript" src="../../include/common/javascript/widgetUtils.js"></script>
		<script type="text/javascript" src="../../include/common/javascript/jquery/plugins/treeTable/jquery.treeTable.min.js"></script>
        <script src="../../include/common/javascript/charts/d3.min.js" language="javascript"></script>
        <script src="../../include/common/javascript/charts/c3.min.js" language="javascript"></script>
        
        
</head>
    <body>
        <div id='global_health'></div>
    </body>    

    <script type="text/javascript">
    var widgetId = <?php echo $widgetId; ?>;
    var autoRefresh = <?php echo $autoRefresh;?>;
    var timeout;
    var itemsPerPage = <?php if(!empty($preferences['entries'])){ echo $preferences['entries']; }else{ echo '50'; }?>;
    var pageNumber = 0;
    var broker = '<?php echo $broker;?>';

    jQuery(function() {
        loadPage();
    });

    /**
     * Load page
     */
    function loadPage()
    {
        var indexPage = "global_health";
            jQuery.ajax("./src/"+indexPage+".php?widgetId="+widgetId, {        
                success : function(htmlData) {
                    jQuery("#global_health").html("");
                    jQuery("#global_health").html(htmlData);
                    //jQuery("#BaTable").styleTable();
                    var h = document.getElementById("global_health").scrollHeight + 30;
                    parent.iResize(window.name, h);
                    jQuery("#global_health img, #global_health style, #global_health script, #global_health link").load(function(){
                        var h = document.getElementById("global_health").scrollHeight + 30;
                        parent.iResize(window.name, h);
                    });
                    
            }
        });
        if (autoRefresh) {
            if (timeout) {
                clearTimeout(timeout);
            }
            timeout = setTimeout(loadPage, (autoRefresh * 1000));
        }
    }
    </script>
</html>