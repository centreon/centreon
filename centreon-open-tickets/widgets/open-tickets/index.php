<?php
/*
 * Copyright 2015 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets 
 * the needs in IT infrastructure and application monitoring for 
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0  
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once "../require.php";
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/modules/centreon-open-tickets/class/rule.php';

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
    $rule = new Centreon_OpenTickets_Rule($db);
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
    	<title>Open Tickets</title>
    	<!--<link href="../../Themes/Centreon-2/jquery-ui/jquery-ui.css" rel="stylesheet" type="text/css"/>-->
    	<!--<link href="../../Themes/Centreon-2/jquery-ui/jquery-ui-centreon.css" rel="stylesheet" type="text/css"/>-->
    	<link href="../../include/common/javascript/jquery/plugins/pagination/pagination.css" rel="stylesheet" type="text/css"/>
    	<link href="../../Themes/Centreon-2/style.css" rel="stylesheet" type="text/css"/>
    	<link href="<?php echo '../../Themes/Centreon-2/Color/blue_css.php';?>" rel="stylesheet" type="text/css"/>
        <link href="<?php echo '../../Themes/Centreon-2/Color/green_css.php';?>" rel="stylesheet" type="text/css"/>
        <link href="<?php echo '../../Themes/Centreon-2/Color/red_css.php';?>" rel="stylesheet" type="text/css"/>
        <link href="<?php echo '../../Themes/Centreon-2/Color/yellow_css.php';?>" rel="stylesheet" type="text/css"/>

    	<script type="text/javascript" src="../../include/common/javascript/jquery/jquery.js"></script>
    	<script type="text/javascript" src="../../include/common/javascript/jquery/jquery-ui.js"></script>
    	<script type="text/javascript" src="../../include/common/javascript/jquery/plugins/pagination/jquery.pagination.js"></script>
    	<!--<script type="text/javascript" src="../../include/common/javascript/widgetUtils.js"></script>-->

    	<style type="text/css">
                 body{ margin:0; padding: 0; font-size: 11px;}
                 * html body { overflow:hidden; }
                 * html div#openTicketsTable { height:100%; overflow:auto; }
                 .ListTable {font-size:11px;border-color: #BFD0E2;}
                 .ListHeader {
                     background: #cfedf9;
                 }
            </style>

    </head>
	<body>
<?php
$result = $rule->getAliasAndProviderId($preferences['rule']);
if (!isset($preferences['rule']) || is_null($preferences['rule']) || $preferences['rule'] == '' ||
    !isset($result['provider_id'])) {
    print "<center><div class='update' style='text-align:center;width:350px;'>"._("Please select a rule first")."</div></center>";
} else {
?>
     <div id='actionBar' style='width:100%;'>
        <span id='toolBar'></span>
        <span id='pagination' class='pagination' style='float:left;width:35%;text-align:center;'> </span>
        <span id='nbRows' style='float:left;width:19%;text-align:right;font-weight:bold;'></span>
    </div>
        <div id='openTicketsTable'></div>
<?php
}
?>
	</body>
<script type="text/javascript">
var widgetId = <?php echo $widgetId; ?>;
var autoRefresh = <?php echo $autoRefresh;?>;
var timeout;
var itemsPerPage = <?php echo $preferences['entries'];?>;
var pageNumber = 0;
var clickedCb = new Array();

jQuery(function() {
	loadToolBar();
	loadPage();
	$('.checkall').live('click', function () {
		var chck = this.checked;
		$(this).parents().find(':checkbox').each(function() {
			$(this).attr('checked', chck);
			clickedCb[$(this).attr('id')] = chck;
		});
	});
	$(".selection").live('click', function() {
		clickedCb[$(this).attr('id')] = this.checked;
	});
});

/**
 * Load page
 */
function loadPage()
{
    var indexPage = "index";
    jQuery.ajax("./src/"+indexPage+".php?widgetId="+widgetId+"&page="+pageNumber, {
            success : function(htmlData) {
                jQuery("#openTicketsTable").html("");
                jQuery("#openTicketsTable").html(htmlData);
                var h = document.getElementById("openTicketsTable").scrollHeight + 30;
                parent.iResize(window.name, h);
        }
	});
	if (autoRefresh) {
		if (timeout) {
			clearTimeout(timeout);
		}
		timeout = setTimeout(loadPage, (autoRefresh * 1000));
	}
}

/**
 * Load toolbar
 */
function loadToolBar()
{
	jQuery("#toolBar").load("./src/toolbar.php",
							{
								widgetId : widgetId
							});
}
</script>
</html>
