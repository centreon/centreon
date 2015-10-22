<?php
/*
 * Copyright 2005-2015 Centreon
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
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 * SVN : $URL$
 * SVN : $Id$
 *
 */

	if (!isset($oreon))
		exit();

	$gmtObj = new CentreonGMT($pearDB);
    /**
     * Notice that this timestamp is actually the server's time and not the UNIX time
     * In the future this behaviour should be changed and UNIX timestamps should be used
     * 
     * date('Z') is the offset in seconds between the server's time and UTC
     * The problem remains that on some servers that do not use UTC based timezone, leap seconds are taken in
     * considerations while all other dates are in comparison wirh GMT so there will be an offset of some seconds
     */
	$currentServerMicroTime = time() * 1000 + date('Z') * 1000;
	$userGmt = 0;
	$useGmt = 0;
	if ($gmtObj->checkGMTStatus($pearDB)) {
        $useGmt = 1;
	    $userGmt = $oreon->user->getMyGMT();
	    $currentServerMicroTime += $userGmt * 60 * 60 * 1000;
	}

	/*
	 * Path to the configuration dir
	 */
	$path = "./include/views/graphs/";

	/*
	 * Include Pear Lib
	 */

	require_once "HTML/QuickForm.php";
    require_once 'HTML/QuickForm/select2.php';
	require_once 'HTML/QuickForm/Renderer/ArraySmarty.php';

	/*
	 * Smarty template Init
	 */
	$tpl = new Smarty();
	$tpl = initSmartyTpl($path, $tpl);

	$openid = '0';
	$open_id_sub = '0';

	if (isset($_GET["openid"])){
		$openid = $_GET["openid"];
		$open_id_type = substr($openid, 0, 2);
		$open_id_sub = substr($openid, 3, strlen($openid));
	}

	(isset($_GET["host_id"]) && $open_id_type == "HH") ? $_GET["host_id"] = $open_id_sub : $_GET["host_id"] = null;

	$id = 1;

	function getGetPostValue($str){
		$value = NULL;
		if (isset($_GET[$str]) && $_GET[$str])
			$value = $_GET[$str];
		if (isset($_POST[$str]) && $_POST[$str])
			$value = $_POST[$str];
		return urldecode($value);
	}

	/*
	 * Get Arguments
	 */

	$id 	= getGetPostValue("id");
	$id_svc = getGetPostValue("svc_id");
	$meta 	= getGetPostValue("meta");
    $search = getGetPostValue("search");
    $search_service = getGetPostValue("search_service");

	if (isset($id_svc) && $id_svc){
		$id = "";
		$tab_svcs = explode(",", $id_svc);
		foreach($tab_svcs as $svc){
			$tmp = explode(";", $svc);
			if (!isset($tmp[1])) {
				$id .= "HH_" . getMyHostID($tmp[0]).",";
			}
			if ((isset($tmp[0]) && $tmp[0] == "") || $meta == 1) {
				$DBRESULT = $pearDB->query("SELECT `meta_id` FROM meta_service WHERE meta_name = '".$tmp[1]."'");
				$res = $DBRESULT->fetchRow();
				$DBRESULT->free();
				$id .= "MS_".$res["meta_id"].",";
				$meta = 1;
			} else {
				if (isset($tmp[1]))
					$id .= "HS_" . getMyServiceID($tmp[1], getMyHostID($tmp[0]))."_".getMyHostID($tmp[0]).",";
			}
		}
	}

	/* Get Period if is in url */
	$period_start = 'undefined';
	$period_end = 'undefined';
	if (isset($_REQUEST['start']) && is_numeric($_REQUEST['start'])) {
	    $period_start = $_REQUEST['start'];
	}
	if (isset($_REQUEST['end']) && is_numeric($_REQUEST['end'])) {
	    $period_end = $_REQUEST['end'];
	}

	/*
	 * Form begin
	 */
	$form = new HTML_QuickForm('FormPeriod', 'get', "?p=".$p);
	$form->addElement('header', 'title', _("Choose the source to graph"));

	$periods = array(	""=>"",
						"10800"		=> _("Last 3 Hours"),
						"21600"		=> _("Last 6 Hours"),
						"43200"		=> _("Last 12 Hours"),
						"86400"		=> _("Last 24 Hours"),
						"172800"	=> _("Last 2 Days"),
						"259200"	=> _("Last 3 Days"),
						"302400"	=> _("Last 4 Days"),
						"432000"	=> _("Last 5 Days"),
						"604800"	=> _("Last 7 Days"),
						"1209600"	=> _("Last 14 Days"),
						"2419200"	=> _("Last 28 Days"),
						"2592000"	=> _("Last 30 Days"),
						"2678400"	=> _("Last 31 Days"),
						"5184000"	=> _("Last 2 Months"),
						"10368000"	=> _("Last 4 Months"),
						"15552000"	=> _("Last 6 Months"),
						"31104000"	=> _("Last Year"));
	$sel = $form->addElement('select', 'period', _("Graph Period"), $periods, array("onchange"=>"resetFields([this.form.StartDate, this.form.StartTime, this.form.EndDate, this.form.EndTime])"));
	$form->addElement('text', 'StartDate', '', array("id"=>"StartDate", "class" => "datepicker", "size"=>10));
	$form->addElement('text', 'StartTime', '', array("id"=>"StartTime", "class"=>"timepicker", "size"=>5));
	$form->addElement('text', 'EndDate', '', array("id"=>"EndDate", "class" => "datepicker", "size"=>10));
	$form->addElement('text', 'EndTime', '', array("id"=>"EndTime", "class"=>"timepicker", "size"=>5));
	$form->addElement('button', 'graph', _("Apply"), array("onclick"=>"apply_period()", "class"=>"btc bt_success"));
    $form->addElement('text', 'search', _('Host'));
    $form->addElement('text', 'search_service', _('Service'));
    
    // Service Selector
    $attrServices = array(
        'datasourceOrigin' => 'ajax',
        'availableDatasetRoute' => './include/common/webServices/rest/internal.php?object=centreon_configuration_service&action=list&g=1',
        'defaultDatasetRoute' => './include/common/webServices/rest/internal.php?object=centreon_configuration_contact&action=defaultValues&target=host&field=host_cs&id=99999',
        'multiple' => true,
    );
    $form->addElement('select2', 'service_selector', _("Services"), array(), $attrServices);
    
    $attrHosts = array(
        'datasourceOrigin' => 'ajax',
        'availableDatasetRoute' => './include/common/webServices/rest/internal.php?object=centreon_configuration_host&action=list',
        'defaultDatasetRoute' => './include/common/webServices/rest/internal.php?object=centreon_configuration_contact&action=defaultValues&target=host&field=host_cs&id=99999',
        'multiple' => true,
    );
    $form->addElement('select2', 'host_selector', _("Hosts"), array(), $attrHosts);
    

	$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
	$form->accept($renderer);

	$tpl->assign('form', $renderer->toArray());
	$tpl->assign('periodORlabel', _("or"));
	$tpl->assign('from', _("From"));
	$tpl->assign('to', _("to"));
	$tpl->assign('Apply', _("Apply"));
	$tpl->display("graphs.ihtml");
    
    $multi = 1;
?>
<script type="text/javascript">
    
    var gmt = <?php echo $userGmt ? $userGmt : 0;?>;
    var useGmt = <?php echo $useGmt;?>;
    var currentMicroTime = <?php echo number_format($currentServerMicroTime, 15, '.', '');?>;
    var $hostsServicesForGraph = [];

    /* Period if in URL */
    var period_start = <?php echo $period_start; ?>;
    var period_end = <?php echo $period_end; ?>;

	var multi 	= <?php echo $multi; ?>;
  	

	// it's fake methode for using ajax system by default
	function mk_pagination(){;}
	function mk_paginationFF(){;}
	function set_header_title(){;}
	function apply_period()
    {
        launchGraph();
	}

function form2ctime(dpart, tpart)
{
    // dpart : MM/DD/YYYY
    // tpart : HH:mm
    var dparts = dpart.split("/");
    var tparts = tpart.split(":");
    return new Date(dparts[2], dparts[0]-1, dparts[1], tparts[0], tparts[1], 0).getTime() - (new Date().getTimezoneOffset() * 60 * 1000);
}

function ctime2date(ctime)
{
    var date = new Date(ctime + (new Date().getTimezoneOffset() * 60 * 1000));
    return date.getMonth()+1 + "/" + date.getDate() + "/" + date.getFullYear();
}

function ctime2time(ctime) {
	var _zero_hour = '';
	var _zero_min = '';
    var date = new Date(ctime + (new Date().getTimezoneOffset() * 60 * 1000));
	if (date.getHours() <= 9) { _zero_hour = '0'; }
	if (date.getMinutes() <= 9) { _zero_min = '0'; }
        return _zero_hour + date.getHours() + ":" + _zero_min + date.getMinutes();
}

function prevPeriod() {
	if (!document.FormPeriod) {
	    return;
	}
        var start;
        var end;
        var period;
        if (document.FormPeriod.period.value) {
                var now = currentMicroTime;
                period = document.FormPeriod.period.value * 1000;
                start = now - period;
        } else {
                end   = form2ctime(document.FormPeriod.EndDate.value, document.FormPeriod.EndTime.value);
                start = form2ctime(document.FormPeriod.StartDate.value, document.FormPeriod.StartTime.value);
                period = end - start;
        }

        end = start;
        start = start - period;

		document.FormPeriod.period.value = "";
        document.FormPeriod.StartDate.value = ctime2date(start);
        document.FormPeriod.StartTime.value = ctime2time(start);
        document.FormPeriod.EndDate.value = ctime2date(end);
        document.FormPeriod.EndTime.value = ctime2time(end);
        apply_period();
}

function nextPeriod() {
	if (!document.FormPeriod) {
	    return;
	}
        var start;
        var end;
        var period;
        if (document.FormPeriod.period.value) {
                var now = currentMicroTime;
                period = document.FormPeriod.period.value * 1000;
                end = now;
        } else {
                end   = form2ctime(document.FormPeriod.EndDate.value, document.FormPeriod.EndTime.value);
                start = form2ctime(document.FormPeriod.StartDate.value, document.FormPeriod.StartTime.value);
                period = end - start;
        }

        start = end;
        end = end + period;

	document.FormPeriod.period.value = "";
        document.FormPeriod.StartDate.value = ctime2date(start);
        document.FormPeriod.StartTime.value = ctime2time(start);
        document.FormPeriod.EndDate.value = ctime2date(end);
        document.FormPeriod.EndTime.value = ctime2time(end);
        apply_period();
}

	// Period
	var currentTime = currentMicroTime;
	var period ='';

	var _zero_hour = '';
	var _zero_min = '';
	var StartDate = '';
	var EndDate = '';
	var StartTime = '';
	var EndTime = '';
	var ms_per_hour = 60 * 60 * 1000;

	if (document.FormPeriod.period.value !== "")	{
		period = document.FormPeriod.period.value;
	} else if (period_start !== undefined && period_end !== undefined) {
		StartDate = ctime2date(period_start * 1000);
		StartTime = ctime2time(period_start * 1000);
		EndDate = ctime2date(period_end * 1000);
		EndTime = ctime2time(period_end * 1000);
	} else {
		EndDate   = ctime2date(currentTime);
		EndTime   = ctime2time(currentTime);
		StartDate = ctime2date(currentTime-12*ms_per_hour);
		StartTime = ctime2time(currentTime-12*ms_per_hour);
	}

	if (document.FormPeriod) {
		document.FormPeriod.StartDate.value = StartDate;
		document.FormPeriod.EndDate.value = EndDate;
		document.FormPeriod.StartTime.value = StartTime;
		document.FormPeriod.EndTime.value = EndTime;
	}

	function graph_4_host(id, multi, l_mselect, pStart, pEnd, metrics)	{
		if (!multi)
			multi = 0;
		// no metric selection : default
		if (l_mselect === undefined) {
			l_select = 0;
		}


		if (pStart && pEnd){
			period = pEnd - pStart;
		} else if (document.FormPeriod.period.value !== "") {
			period = document.FormPeriod.period.value;
		} else if (document.FormPeriod) {
			period = '';
			StartDate = document.FormPeriod.StartDate.value;
			EndDate = document.FormPeriod.EndDate.value;
			StartTime = document.FormPeriod.StartTime.value;
			EndTime = document.FormPeriod.EndTime.value;
		}

		if (StartTime === "") {
       		StartTime = "00:00";
        }
        if (EndTime === "") {
        	EndTime = "23:59";
        }
        
        // Get search information
        /*var search = encodeURI(document.FormPeriod.search.value);
        var search_service = encodeURI(document.FormPeriod.search_service.value);*/

		// Metrics
		var _metrics ="";
		if (metrics) {
			_metrics += '&metric['+metrics+']=1';
			//multi = 1;
		} else {
			if (l_mselect) {
				var _checked = "0";
				if (document.formu3 && document.formu3.elements["metric"]){
					//multi = 1;
					for (i=0; i < document.formu3.elements["metric"].length; i++) {
						_checked = "0";
						if (document.formu3.elements["metric"][i].checked)	{
							_checked = "1";
						}
						_metrics += '&metric['+document.formu3.elements["metric"][i].value+']='+_checked ;
					}
				}
			}
		}

		// Templates
		var _tpl_id = 1;
		if (document.formu2 && document.formu2.template_select && document.formu2.template_select.value !== ""){
			_tpl_id = document.formu2.template_select.value;
		}

		// preg_split metric
		var _split = 0;
		if (document.formu2 && document.formu2.split && document.formu2.split.checked)	{
			_split = 1;
		}

		var _status = 0;
		if (document.formu2 && document.formu2.status && document.formu2.status.checked)	{
			_status = 1;
		}

		var _warning = 0;
		if (document.formu2 && document.formu2.warning && document.formu2.warning.checked)	{
			_warning = 1;
		}

		var _critical = 0;
		if (document.formu2 && document.formu2.critical && document.formu2.critical.checked)	{
			_critical = 1;
		}

		var proc = new Transformation();
		var _addrXSL = "./include/views/graphs/graph.xsl";
		var _addrXML = './include/views/graphs/GetXmlGraph.php?multi='+multi+'&split='+_split+'&status='+_status+'&warning='+_warning+'&critical='+_critical+_metrics+'&template_id='+_tpl_id +'&period='+period+'&StartDate='+StartDate+'&EndDate='+EndDate+'&StartTime='+StartTime+'&EndTime='+EndTime+'&id='+id+'&sid=<?php echo $sid;?><?php if ($focusUrl) print "&focusUrl=".urlencode($focusUrl);?>';
        /*if (search) {
            _addrXML += '&search=' + search;
        }
        if (search_service) {
            _addrXML += '&search_service=' + search_service;
        }*/

		proc.setXml(_addrXML);
		proc.setXslt(_addrXSL);
		proc.transform("graphView4xml");
		list_img = new Hash();
	}

	jQuery(function () {
	    // Here is your precious function
	    // You can call as many functions as you want here;
	    myOnloadFunction1();
	});

    // Your precious function
    function myOnloadFunction1() {}

    /* Graph zoom*/
    var margeLeftGraph = 67;
    var margeTopGraph = 27;
    var margeRightGraph = 24;
    var margeBottomGraph = 82;
    var list_img = new Hash();

    /**
     * Add zoom to graph img_name
     *
     * @var img_name The tag name
     */
    function addGraphZoom(img_name) {
        if ($(img_name).ancestors()[0].match('a')) {
        	$(img_name).ancestors()[0].setAttribute('onClick', 'return false;');
        }
        var maxheight = document.getElementById(img_name).offsetHeight;
    	list_img.set(img_name, new Cropper.Img(img_name, {
    		minHeight: maxheight,
    		maxHeight: maxheight,
    		onEndCrop: function(coords, dim, self){
    			var basename = self.gsub(/(.*)__M:.*/, function(matches){
        			return(matches[1] + "__M:");
    			});
    			$$("img[id^=" + basename + "]").each(function(el) {
        			if (el.id !== self) {
            			var elHeight = el.height;
            			list_img.get(el.id).setArea(coords.x1, 0, coords.x2, elHeight);
        			}
    			});
        	}
    	}));
    	var parent = $(img_name).ancestors()[0];
    	parent.style.setProperty("margin", "0 auto", "");
    	//parent.style.setProperty("margin-right", "auto", "");
    	//parent.style.setProperty("margin-left", "auto", "");
    }


    /**
     * Call the zoom
	 *
     * @var img_name The tag name
     */
    function toGraphZoom(img_name) {
        mutli = 0;
    	var s_multi = true;
        if ($$("img[id=" + img_name + "]").size() === 0) {
            var s_multi = false;
            var tmplist = $$("img[id^=" + img_name + "]");
            if (tmplist.size() === 0) {
                return(false);
            }
            img_name = tmplist[0].id;
        }
        var coords = list_img.get(img_name).areaCoords;
        var img_url = $(img_name).src.parseQuery();
        var period = (img_url.end * 1000) - (img_url.start * 1000);
        var zoneGraph = $(img_name).width - margeLeftGraph - margeRightGraph;
        if (coords.x1 < margeLeftGraph || coords.x1 > ($(img_name).width - margeRightGraph)) {
            return(false);
        }
        if (coords.x2 < margeLeftGraph || coords.x2 > ($(img_name).width - margeRightGraph)) {
            return(false);
        }
        var start = parseInt((img_url.start * 1000) + ((coords.x1 - margeLeftGraph) * period / ($(img_name).width - margeLeftGraph - margeRightGraph)));
        var end = parseInt((img_url.start * 1000) + ((coords.x2 - margeLeftGraph) * period / ($(img_name).width - margeLeftGraph - margeRightGraph)));

        if (useGmt) {
            start += gmt * 60 * 60 * 1000;
            end += gmt * 60 * 60 * 1000;
        }
        //@todo: this is a quick & dirty fix for countering ctime2date()
        gmtSec = new Date().getTimezoneOffset() * 60 * 1000 * -1;
        start += gmtSec;
        end += gmtSec;

        var id = img_name.split('__')[0];
        id = id.replace('HS_', 'SS_');

        document.FormPeriod.period.selectedIndex = 0;
        document.FormPeriod.StartDate.value = ctime2date(start);
		document.FormPeriod.EndDate.value = ctime2date(end);
		document.FormPeriod.StartTime.value = ctime2time(start);
		document.FormPeriod.EndTime.value = ctime2time(end);
		if (img_name.indexOf('__M:') !== -1 && s_multi) {
            metrics = img_name.substring(img_name.indexOf('__M:') + 4);
            graph_4_host(id, 0, null, "", "", metrics);
            return false;
        }
        graph_4_host(id, 0);
        return false;
    }

    function switchZoomGraph(tag_name) {
        $("zoom_" + tag_name).setAttribute("onClick", "toGraphZoom('" + tag_name + "'); return false;");
        if ($(tag_name) !== null) {
            if (list_img.get(tag_name) === undefined) {
        		addGraphZoom(tag_name);
            }
        	return false;
        }
        $$("img[id^=" + tag_name + "]").each(function(el) { if (list_img.get(el.id) === undefined) { addGraphZoom(el.id); } });
        return false;
    }
    
    function launchGraph() {
        $hostsServices = '';
        jQuery.each($hostsServicesForGraph, function(index, value) {
            $hostsServices += value + ',';
        });
        graph_4_host($hostsServices, 1);
    };
    
    jQuery("#service_selector").on("change", function() {
        $selectedOptions = jQuery(this).val();
        
        jQuery.each($selectedOptions, function(index, value) {
            $splittedValue = value.split('-');
            finalValue = 'HS_' + $splittedValue[1] + '_' + $splittedValue[0];
            if (jQuery.inArray(finalValue, $hostsServicesForGraph) === -1) {
                $hostsServicesForGraph.push(finalValue);
            }
        });
        launchGraph();
    });
    
    jQuery("#host_selector").on("select2:unselecting", function(event, data) {
        /*$myHostPattern = 'HS_' + event.params.args.data.id;
        console.log(event.params.args.data);
        jQuery.each($hostsServicesForGraph, function(index, value) {
            if (value.indexOf($myHostPattern) !== -1) {
                
            }
        });*/
    });
    
    jQuery("#host_selector").on("change", function() {
        $selectedOptions = jQuery(this).val();
        
        // Get all connected services
        jQuery.each($selectedOptions, function(index, value) {
            jQuery.ajax({
                url: './include/common/webServices/rest/internal.php?object=centreon_configuration_host&action=services&id=' + value + '&g=1',
                success: function(data) {
                    jQuery.each(data, function(id, description) {
                        finalValue = 'HS_' + id + '_' + value;
                        if (jQuery.inArray(finalValue, $hostsServicesForGraph) === -1) {
                            $hostsServicesForGraph.push(finalValue);
                        }
                    });
                    launchGraph();
                }
            });
        });
    });
    
    function resetFields(fields) {
        for(i=0;i<fields.length;i++ ) {
            fields[i].value="";
        }
    }

    
</script>
