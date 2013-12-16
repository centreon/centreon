<?php
/*
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
 * SVN : $URL$
 * SVN : $Id$
 *
 */

function getChildren($infos) {
    global $pearDB;
    if ($infos['subgrp_id'] == '') {
        return null;
    }
    $children = array();
    $level = $infos['grp_level'] + 1;
    $query = "SELECT config_key, config_value, config_id, config_group, config_group_id, grp_level, subgrp_id
        FROM cfg_centreonbroker_info
        WHERE grp_level = " . $level . "
        AND config_id = " . $infos['config_id'] . "
        AND config_group = '" . $infos['config_group'] . "'
	AND config_group_id = " . $infos['config_group_id'] . "
	AND parent_grp_id = " . $infos['subgrp_id'];
    $res = $pearDB->query($query);
    if (PEAR::isError($res)) {
        return null;
    }
    while ($row = $res->fetchRow()) {
        if (!isset($children[$row['config_key']])) {
            $children[$row['config_key']] = array(
		'key' => $row['config_key'],
	        'children' => getChildren($row),
		'values' => $row['config_value']
            );
	} else {
            if (!is_array($children[$row['config_key']]['values'])) {
                $children[$row['config_key']]['values'] = array($children[$row['config_key']]['values']);
	    }
            $children[$row['config_key']]['values'][] = $row['config_value'];
	}
    }
    $result = array();
    foreach ($children as $key => $child) {
        $result[] = $child;
    }
    if (count($result) === 0) {
        return null;
    }
    return $result;
}

function writeElement($xml, $element) {
    if ($element['key'] != 'blockId') {
	if (!is_null($element['children'])) {
            $xml->startElement($element['key']);
	    foreach ($element['children'] as $child) {
                writeElement($xml, $child);
            }
	    $xml->endElement();
	} elseif (is_array($element['values'])) {
            foreach ($element['values'] as $value) {
                $xml->writeElement($element['key'], $value);
            }
	} elseif (trim($element['values']) != '') {
            $xml->writeElement($element['key'], $element['values']);
	}
    }
}

    if (!isset($oreon)) {
		exit();
	}

	/*
	 * Connect to MySQL
	 */
	$pearDBO = new CentreonDB("centstorage");

	require_once $centreon_path . "/www/class/centreonXML.class.php";

	$dir_conf = $centreonBrokerPath . '/' . $tab['id'];

	if (!is_dir($dir_conf) && is_writable($centreonBrokerPath)) {
	    mkdir($dir_conf);
	}

	$ns_id = $tab['id'];

	$files = array();
        $eventQueueMaxSize = array();
        
	/*
	 * Get the module path for the nagios_server
	 */
	$query = "SELECT centreonbroker_module_path FROM nagios_server WHERE id = " . $ns_id;
	$res = $pearDB->query($query);
	$centreonBrokerModulePath = null;
	if (false === PEAR::isError($res) && $res->numRows() == 1) {
	    $row = $res->fetchRow();
	    if (trim($row['centreonbroker_module_path']) != '') {
	        $centreonBrokerModulePath = trim($row['centreonbroker_module_path']);
	    }
	}

	/*
	 * Init Broker configuration object
	 */
	$cbObj = new CentreonConfigCentreonBroker($pearDB);

	$query = "SELECT cs.config_filename, cs.config_write_thread_id, cs.config_write_timestamp, cs.event_queue_max_size, csi.config_key, csi.config_value, csi.config_id, csi.config_group, csi.config_group_id, csi.grp_level, csi.subgrp_id , ns.name
		FROM cfg_centreonbroker_info csi, cfg_centreonbroker cs, nagios_server ns
		WHERE csi.config_id = cs.config_id AND cs.config_activate = '1' AND cs.ns_nagios_server = ns.id AND csi.grp_level = 0 AND cs.ns_nagios_server = " . $ns_id;

	$res = $pearDB->query($query);

	$blocks = array();
        if (false === PEAR::isError($res) && $res->numRows()) {
	    $ns_name = null;
	    while ($row = $res->fetchRow()) {
    	    $filename = $row['config_filename'];
            if (!isset($files[$filename])) {
                foreach ($cbObj->getTags() as $tagId => $tagName) {
                    $files[$filename][$tagName] = array();
                }
            }
	    if (is_null($ns_name)) {
	        $ns_name = $row['name'];
	    }
	    if ($row['config_key'] == 'blockId') {
	        if (false === isset($blocks[$row['config_value']])) {
	            $blocks[$row['config_value']] = array();
	        }
	        $blocks[$row['config_value']][] = array(
	            'filename' => $filename,
	            'config_group' => $row['config_group'],
	            'config_group_id' => $row['config_group_id']
	        );
	    }
	    $infos = array(
                'key' => $row['config_key'],
		'children' => getChildren($row),
		'values' => $row['config_value']
	    );
	    $files[$filename][$row['config_group']][$row['config_group_id']][] = $infos;
            $eventQueueMaxSize[$filename] = $row['event_queue_max_size'];
            $logTimestamp[$filename] = $row['config_write_timestamp'];
            $logThreadId[$filename] = $row['config_write_thread_id'];
        }

	/*
	 * Replace globals values
	 */
	// @TODO
	/* foreach ($blocks as $blockId => $block) {
	    list($tagId, $typeId) = explode('_', $blockId);
            $fields = $cbObj->getBlockInfos($typeId);
            foreach ($fields as $field) {
                if (!is_null($field['value'])) {
                    $default = $cbObj->getInfoDb($field['value']);
                    if (trim($default) != '') {
                        foreach ($block as $infos) {
                            $files[$infos['filename']][$infos['config_group']][$infos['config_group_id']][$field['fieldname']] = $default;
                        }
                    }
                }
            }
	} */

    	/*
	     * Delete all old files.
	     */
	    foreach (glob("$dir_conf/*") as $filename) {
	        unlink($filename);
	    }

	    
	    foreach ($files as $filename => $groups) {
    	    $fileXml = new CentreonXML(true);
    	    $fileXml->startElement('centreonBroker');

    	    $fileXml->writeElement('instance', $ns_id);
    	    $fileXml->writeElement('instance_name', $ns_name);

    	    if (!is_null($centreonBrokerModulePath)) {
    	        $fileXml->writeElement('module_directory', $centreonBrokerModulePath);
    	    }

            if (isset($eventQueueMaxSize[$filename])) {
                $fileXml->writeElement('event_queue_max_size', $eventQueueMaxSize[$filename]);
            }
            
            if (isset($logTimestamp[$filename])) {
                $fileXml->writeElement('log_timestamp', $logTimestamp[$filename]);
            }
            
            if (isset($logThreadId[$filename])) {
                $fileXml->writeElement('log_thread_id', $logThreadId[$filename]);
            }
            
    	    foreach ($groups as $group => $listInfos) {
    	        if (count($listInfos) > 0) {
        	        foreach ($listInfos as $key2 => $infos) {
        	            $fileXml->startElement($group);
        	            foreach ($infos as $value) {
			        writeElement($fileXml, $value);
        	            }
        	            $fileXml->endElement();
        	        }
    	        }
    	    }
    	    $fileXml->endElement();

    	    ob_start();
            $fileXml->output();
            file_put_contents($dir_conf . '/' . $filename, ob_get_contents());
            ob_end_clean();
	    }
	}
?>
