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
 */

if (!isset($centreon)) {
    exit();
}

include_once _CENTREON_PATH_."www/class/centreonGMT.class.php";
include_once _CENTREON_PATH_."www/class/centreonDB.class.php";

/*
 * Init GMT class
 */
$centreonGMT = new CentreonGMT($pearDB);
$centreonGMT->getMyGMTFromSession(session_id(), $pearDB);
$hostStr = $centreon->user->access->getHostsString("ID", $pearDBO);

if ($centreon->user->access->checkAction("host_schedule_downtime")) {
    /*
     * Init
     */
    if (isset($_GET["host_name"])) {
        $host_id = getMyHostID($_GET["host_name"]);
        $host_name = $_GET["host_name"];
    } else
        $host_name = NULL;

        $data = array(  "start" => $centreonGMT->getDate("Y/m/d" , time() + 120), 
                        "end" => $centreonGMT->getDate("Y/m/d", time() + 7320),
                        "start_time" => $centreonGMT->getDate("G:i" , time() + 120),
                        "end_time" => $centreonGMT->getDate("G:i" , time() + 7320),
                        "host_or_hg" => '1',
                        "with_services" => '0'
                    );
        if (isset($host_id)) {
            $data["host_id"] = $host_id;
        }

        /*
         * Database retrieve information for differents elements list we need on the page
         */
        $hosts = array();
        $query = "SELECT host_id, host_name " .
                "FROM `host` " .
                "WHERE host_register = '1' " .
                $centreon->user->access->queryBuilder("AND", "host_id", $hostStr) .
                "ORDER BY host_name";
        $DBRESULT = $pearDB->query($query);
        while ($host = $DBRESULT->fetchRow()){
            $hosts[$host["host_id"]]= $host["host_name"];
        }
        $DBRESULT->free();

        /*
         * Get the list of hostgroup
         */
        $hg = array();
        if ($centreon->user->access->admin) {
            $query = "SELECT hg_id, hg_name
                      FROM hostgroup
                      WHERE hg_activate = '1' 
                      ORDER BY hg_name";
        } else {
            $query = "SELECT DISTINCT hg.hg_id, hg.hg_name " .
                     "FROM hostgroup hg, acl_resources_hg_relations arhr " .
                     "WHERE hg.hg_id = arhr.hg_hg_id " .
                     "AND arhr.acl_res_id IN (".$centreon->user->access->getResourceGroupsString().") " .
                     "AND hg.hg_activate = '1' ".
                     "AND hg.hg_id in (SELECT hostgroup_hg_id
                       FROM hostgroup_relation
                       WHERE host_host_id IN (".$centreon->user->access->getHostsString("ID", "broker").")) " .
                     "ORDER BY hg.hg_name";
        }
        $res = $pearDB->query($query);
        while ($row = $res->fetchRow()) {
            $hg[$row['hg_id']] = $row['hg_name'];
        }
        $res->free();

        $debug = 0;
        $attrsTextI     = array("size"=>"3");
        $attrsText      = array("size"=>"30");
        $attrsTextarea  = array("rows"=>"7", "cols"=>"100");

        /*
         * Form begin
         */
        $form = new HTML_QuickForm('Form', 'POST', "?p=".$p);
        if ($o == "ah") {
            $form->addElement('header', 'title', _("Add a Host downtime"));
        }

        /*
         * Indicator basic information
         */
        $redirect = $form->addElement('hidden', 'o');
        $redirect->setValue($o);

        $host_or_hg[] = HTML_QuickForm::createElement('radio', 'host_or_hg', null, _("Host"), '1', array('id' => 'host_or_hg_host', 'onclick' => "toggleParams('host');"));
        $host_or_hg[] = HTML_QuickForm::createElement('radio', 'host_or_hg', null, _("Hostgroup"), '0', array('id' => 'host_or_hg_hg', 'onclick' => "toggleParams('hostgroup');"));
        //$host_or_hg[] = HTML_QuickForm::createElement('radio', 'poller', null, _("Poller Hosts"), '2', array('id' => 'poller', 'onclick' => "toggleParams('poller');"));
        $form->addGroup($host_or_hg, 'host_or_hg', _("Select a downtime type"), '&nbsp;');

        
        // uncomment this section : the user can choose to set a downtime based on the host time or the centreon user time.
        /*
        $host_or_centreon_time[] = HTML_QuickForm::createElement('radio', 'host_or_centreon_time', null, _("Centreon Time"), '0');
        $host_or_centreon_time[] = HTML_QuickForm::createElement('radio', 'host_or_centreon_time', null, _("Host Time"), '1');
        $form->addGroup($host_or_centreon_time, 'host_or_centreon_time', _("Select Host or Centreon Time"), '&nbsp;');        
        $form->setDefaults(array('host_or_centreon_time' => '0'));   
        */
        
        /* ----- Hosts ----- */
        $attrHosts = array(
            'datasourceOrigin' => 'ajax',
            'availableDatasetRoute' => './include/common/webServices/rest/internal.php?object=centreon_configuration_host&action=list',
            'multiple' => true,
            'linkedObject' => 'centreonHost'
        );
        $attrHost1 = array_merge(
            $attrHosts,
            array('defaultDatasetRoute' => './include/common/webServices/rest/internal.php?object=centreon_configuration_host&action=defaultValues&target=service&field=service_hPars&id=' . $service_id)
        );
        $form->addElement('select2', 'host_id', _("Host Name"), array(), $attrHost1);
        
        /* ----- HostGroups ----- */
        $attrHostgroups = array(
            'datasourceOrigin' => 'ajax',
            'availableDatasetRoute' => './include/common/webServices/rest/internal.php?object=centreon_configuration_hostgroup&action=list',
            'multiple' => true,
            'linkedObject' => 'centreonHostgroups'
        );
        $attrHostgroup1 = array_merge(
            $attrHostgroups,
            array('defaultDatasetRoute' => './include/common/webServices/rest/internal.php?object=centreon_configuration_hostgroup&action=defaultValues&target=service&field=service_hgPars&id=' . $service_id)
        );
        $form->addElement('select2', 'hostgroup_id', _("Hostgroup"), array(), $attrHostgroup1);

        /* ----- Pollers ----- */
        /*
        $attrHPollers = array(
            'datasourceOrigin' => 'ajax',
            'availableDatasetRoute' => './include/common/webServices/rest/internal.php?object=centreon_configuration_poller&action=list',
            'multiple' => true,
            'linkedObject' => 'centreonPollers'
        );
        $attrHPollers1 = array_merge(
            $attrHPollers,
            array('defaultDatasetRoute' => './include/common/webServices/rest/internal.php?object=centreon_configuration_poller&action=defaultValues&target=service&field=service_hgPars&id=' . $service_id)
        );
        $form->addElement('select2', 'poller_id', _("Pollers"), array(), $attrHPollers1);
        */

        $chbx = $form->addElement('checkbox', 'persistant', _("Fixed"), null, array('id' => 'fixed', 'onClick' => 'javascript:setDurationField()'));
        if (isset($centreon->optGen['monitoring_dwt_fixed']) && $centreon->optGen['monitoring_dwt_fixed']) {
            $chbx->setChecked(true);
        }
        $form->addElement('text', 'start', _("Start Time"), array('size' => 10, 'class' => 'datepicker'));
        $form->addElement('text', 'end', _("End Time"), array('size' => 10, 'class' => 'datepicker'));                    
        $form->addElement('text', 'start_time', '', array('size' => 5, 'class' => 'timepicker'));
        $form->addElement('text', 'end_time', '', array('size' => 5, 'class' => 'timepicker'));
        $form->addElement('text', 'duration', _("Duration"), array('size' => '15', 'id' => 'duration'));
        $defaultDuration = 3600;
        
        if (isset($centreon->optGen['monitoring_dwt_duration']) && $centreon->optGen['monitoring_dwt_duration']) {
            $defaultDuration = $centreon->optGen['monitoring_dwt_duration'];
        }
        $form->setDefaults(array('duration' => $defaultDuration));
        
        $form->addElement('select', 'duration_scale', _("Scale of time"), array("s" => _("seconds"), "m" => _("minutes"), "h" => _("hours"), "d" => _("days")));
        $defaultScale = 's';
        if (isset($centreon->optGen['monitoring_dwt_duration_scale']) && $centreon->optGen['monitoring_dwt_duration_scale']) {
            $defaultScale = $centreon->optGen['monitoring_dwt_duration_scale'];
        }
        $form->setDefaults(array('duration_scale' => $defaultScale));

        $with_services[] = HTML_QuickForm::createElement('radio', 'with_services', null, _("Yes"), '1');
        $with_services[] = HTML_QuickForm::createElement('radio', 'with_services', null, _("No"), '0');
        $form->addGroup($with_services, 'with_services', _("Set downtime for hosts services"), '&nbsp;');

        $form->addElement('textarea', 'comment', _("Comments"), $attrsTextarea);

        $form->addRule('end', _("Required Field"), 'required');
        $form->addRule('start', _("Required Field"), 'required');
        $form->addRule('end_time', _("Required Field"), 'required');
        $form->addRule('start_time', _("Required Field"), 'required');
        $form->addRule('comment', _("Required Field"), 'required');

        $form->setDefaults($data);
        $subA = $form->addElement('submit', 'submitA', _("Save"));
        $res = $form->addElement('reset', 'reset', _("Reset"));

        /* Push the downtime */
        if ((isset($_POST["submitA"]) && $_POST["submitA"]) && $form->validate()) {
            $values = $form->getSubmitValues();
            if (!isset($_POST["persistant"]) || !in_array($_POST["persistant"], array('0', '1'))) {
                $_POST["persistant"] = '0';
            }
            if (!isset($_POST["comment"])) {
                $_POST["comment"] = 0;
            }

            $_POST["comment"] = str_replace("'", " ", $_POST['comment']);
            $duration = null;
            if (isset($_POST['duration'])) {
                if (isset($_POST['duration_scale'])) {
                    $duration_scale = $_POST['duration_scale'];
                } else {
                    $duration_scale = 's';
                }
                
                switch ($duration_scale) {
                    default:
                    case 's':
                        $duration = $_POST['duration'];
                        break;
                    
                    case 'm':
                        $duration = $_POST['duration'] * 60;
                        break;
                    
                    case 'h':
                        $duration = $_POST['duration'] * 60 * 60;
                        break;
                    
                    case 'd':
                        $duration = $_POST['duration'] * 60 * 60 * 24;
                        break;
                }
            }
            isset($_POST['host_or_centreon_time']['host_or_centreon_time']) && $_POST['host_or_centreon_time']['host_or_centreon_time'] ? $host_or_centreon_time = $_POST['host_or_centreon_time']['host_or_centreon_time'] : $host_or_centreon_time = "0";
            
            $dt_w_services = false;
            if ($values['with_services']['with_services'] == 1) {
                $dt_w_services = true;
            }
            if ($values['host_or_hg']['host_or_hg'] == 1) {
                /*
                 * Set a downtime for only host
                 */
                foreach ($_POST["host_id"] as $host_id) {
                    $ecObj->addHostDowntime(
                            $host_id, 
                            $_POST["comment"], 
                            $_POST["start"].' '.$_POST['start_time'], 
                            $_POST["end"].' '.$_POST['end_time'], 
                            $_POST["persistant"], 
                            $duration, 
                            $dt_w_services,
                            $host_or_centreon_time
                    );
                }
            } else if ($values['host_or_hg']['host_or_hg'] == 0) {
                /*
                 * Set a downtime for hostgroup
                 */
                $hg = new CentreonHostgroups($pearDB);
                foreach ($_POST['hostgroup_id'] as $hg_id) {
                    $hostlist = $hg->getHostGroupHosts($hg_id);
                    $host_acl_id = preg_split('/,/', str_replace("'", "", $hostStr));
                    foreach ($hostlist as $host_id) {
                        if ($centreon->user->access->admin || in_array($host_id, $host_acl_id)) {
                            $ecObj->addHostDowntime(
                                $host_id, 
                                $_POST["comment"], 
                                $_POST["start"] . ' '. $_POST["start_time"], 
                                $_POST["end"] . ' ' . $_POST["end_time"], 
                                $_POST["persistant"], 
                                $duration, 
                                $dt_w_services,
                                $host_or_centreon_time
                            );
                        }
                    }                    
                }
            } else {
                /*
                 * Set a downtime for poller
                 */
                foreach ($_POST['poller_id'] as $poller_id) {
                    $DBRESULT = $pearDBO->query("SELECT host_id FROM hosts WHERE poller_id = poller_id AND enabled = 1");
                    $host_acl_id = preg_split('/,/', str_replace("'", "", $hostStr));
                    while ($row = $DBRESULT->fetchRow()) {
                        if ($centreon->user->access->admin || isset($host_acl_id[$host_id])) {
                            $ecObj->addHostDowntime(
                                $row['host_id'], 
                                $_POST["comment"], 
                                $_POST["start"] . ' '. $_POST["start_time"], 
                                $_POST["end"] . ' ' . $_POST["end_time"], 
                                $_POST["persistant"], 
                                $duration, 
                                $dt_w_services,
                                $host_or_centreon_time
                            );
                        }
                    }                    
                }
            }
            require_once("listDowntime.php");
        } else {
            /*
             * Smarty template Init
             */
            $tpl = new Smarty();
            $tpl = initSmartyTpl($path, $tpl, "template/");

            /*
             * Apply a template definition
             */
            $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
            $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
            $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
            $form->accept($renderer);
            $tpl->assign('form', $renderer->toArray());
            $tpl->assign('seconds', _("seconds"));
            $tpl->assign('o', $o);
            $tpl->display("AddHostDowntime.ihtml");
        }
    } else {
        require_once("../errors/alt_error.php");
    }
?>
<script type='text/javascript'>

jQuery(function() {
    setDurationField();
});

function setDurationField()
{
    var durationField = document.getElementById('duration');
    var fixedCb = document.getElementById('fixed');

    if (fixedCb.checked == true) {
        durationField.disabled = true;
    } else {
        durationField.disabled = false;
    }
}
</script>
