<?php
/*
 * Copyright 2005-2015 CENTREON
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
 * As a special exception, the copyright holders of this program give CENTREON
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of CENTREON choice, provided that
 * CENTREON also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

namespace CentreonClapi;

use Centreon_Object_Host_Extended;
use Centreon_Object_Host_Macro_Custom;
use Centreon_Object_Relation_Contact_Group_Host;
use Centreon_Object_Relation_Contact_Host;
use Centreon_Object_Relation_Host_Group_Host;
use Centreon_Object_Relation_Host_Service;
use Centreon_Object_Relation_Host_Template_Host;
use Exception;
use PDOException;
use Pimple\Container;

require_once "centreonHost.class.php";

/**
 * Class
 *
 * @class CentreonHostTemplate
 * @package CentreonClapi
 */
class CentreonHostTemplate extends CentreonHost
{
    /** @var string[] */
    public static $aDepends = ['CMD', 'TP', 'TRAP', 'INSTANCE'];

    /**
     * CentreonHostTemplate constructor
     *
     * @param Container $dependencyInjector
     *
     * @throws PDOException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->params['host_register'] = '0';
        $this->register = 0;
        $this->action = "HTPL";
    }

    /**
     * Will throw an exception if set instance is called
     *
     * @throws CentreonClapiException
     */
    public function setinstance($parameters = null)
    {
        throw new CentreonClapiException(self::UNKNOWN_METHOD);
    }

    /**
     * Export
     *
     * @param mixed|null $filterName
     *
     * @return void
     * @throws Exception
     */
    public function export(mixed $filterName = null): void
    {
        if (!$this->canBeExported($filterName)) {
            return;
        }

        $labelField = $this->object->getUniqueLabelField();
        $filters = ["host_register" => $this->register];
        if (!is_null($filterName)) {
            $filters[$labelField] = $filterName;
        }
        $elements = $this->object->getList(
            "*",
            -1,
            0,
            $labelField,
            'ASC',
            $filters,
            "AND"
        );
        $extendedObj = new Centreon_Object_Host_Extended($this->dependencyInjector);
        $macroObj = new Centreon_Object_Host_Macro_Custom($this->dependencyInjector);

        foreach ($elements as $element) {
            // add host template
            $addStr = $this->action . $this->delim . "ADD";
            foreach ($this->insertParams as $param) {
                $addStr .= $this->delim;
                if (isset($element[$param]) && $param != "hostgroup" && $param != "template") {
                    $addStr .= $element[$param];
                }
            }
            $addStr .= "\n";
            echo $addStr;

            CentreonExported::getInstance()->setExported($this->action, $element['host_id']);

            // host template params
            foreach ($element as $parameter => $value) {
                if (!in_array($parameter, $this->exportExcludedParams) && !is_null($value) && $value != "") {
                    $action_tmp = null;
                    if ($parameter == "timeperiod_tp_id" || $parameter == "timeperiod_tp_id2") {
                        $action_tmp = 'TP';
                        $tmpObj = CentreonTimePeriod::getInstance();
                    } elseif ($parameter == "command_command_id" || $parameter == "command_command_id2") {
                        $action_tmp = 'CMD';
                        $tmpObj = CentreonCommand::getInstance();
                    } elseif ($parameter == 'host_location') {
                        $tmpObj = CentreonTimezone::getInstance();
                    }
                    if (isset($tmpObj)) {
                        $tmpLabelField = $tmpObj->getObject()->getUniqueLabelField();
                        $tmp = $tmpObj->getObject()->getParameters($value, $tmpLabelField);
                        if (isset($tmp) && isset($tmp[$tmpLabelField])) {
                            $value = $tmp[$tmpLabelField];
                            if (!is_null($action_tmp)) {
                                $tmpObj::getInstance()->export($value);
                            }
                        }
                        unset($tmpObj);
                    }
                    $value = CentreonUtils::convertLineBreak($value);
                    echo $this->action . $this->delim
                        . "setparam" . $this->delim
                        . $element[$this->object->getUniqueLabelField()] . $this->delim
                        . $this->getClapiActionName($parameter) . $this->delim
                        . $value . "\n";
                }
            }

            $params = $extendedObj->getParameters(
                $element[$this->object->getPrimaryKey()],
                ["ehi_notes", "ehi_notes_url", "ehi_action_url", "ehi_icon_image", "ehi_icon_image_alt", "ehi_vrml_image", "ehi_statusmap_image", "ehi_2d_coords", "ehi_3d_coords"]
            );
            if (isset($params) && is_array($params)) {
                foreach ($params as $k => $v) {
                    if (!is_null($v) && $v != "") {
                        $v = CentreonUtils::convertLineBreak($v);
                        echo $this->action . $this->delim
                            . "setparam" . $this->delim
                            . $element[$this->object->getUniqueLabelField()] . $this->delim
                            . $this->getClapiActionName($k) . $this->delim
                            . $v . "\n";
                    }
                }
            }

            // macros linked
            $macros = $macroObj->getList(
                "*",
                -1,
                0,
                null,
                null,
                ['host_host_id' => $element[$this->object->getPrimaryKey()]],
                "AND"
            );
            foreach ($macros as $macro) {
                $description = $macro['description'];
                if (
                    strlen($description) > 0
                    && !str_starts_with($description, "'")
                    && !str_ends_with($description, "'")
                ) {
                    $description = "'" . $description . "'";
                }

                echo $this->action . $this->delim
                    . "setmacro" . $this->delim
                    . $element[$this->object->getUniqueLabelField()] . $this->delim
                    . $this->stripMacro($macro['host_macro_name']) . $this->delim
                    . $macro['host_macro_value'] . $this->delim
                    . ((strlen($macro['is_password']) === 0) ? 0 : (int) $macro['is_password']) . $this->delim
                    . $description . "\n";
            }
        }

        // contact groups linked
        $cgRel = new Centreon_Object_Relation_Contact_Group_Host($this->dependencyInjector);
        $filters_cgRel = ["host_register" => $this->register];
        if (!is_null($filterName)) {
            $filters_cgRel['host_name'] = $filterName;
        }
        $cgElements = $cgRel->getMergedParameters(
            ["cg_name", "cg_id"],
            [$this->object->getUniqueLabelField()],
            -1,
            0,
            null,
            null,
            $filters_cgRel,
            "AND"
        );
        foreach ($cgElements as $cgElement) {
            CentreonContactGroup::getInstance()->export($cgElement['cg_name']);
            echo $this->action . $this->delim
                . "addcontactgroup" . $this->delim
                . $cgElement[$this->object->getUniqueLabelField()] . $this->delim
                . $cgElement['cg_name'] . "\n";
        }

        // contacts linked
        $contactRel = new Centreon_Object_Relation_Contact_Host($this->dependencyInjector);
        $filters_contactRel = ["host_register" => $this->register];
        if (!is_null($filterName)) {
            $filters_contactRel['host_name'] = $filterName;
        }
        $contactElements = $contactRel->getMergedParameters(
            ["contact_alias", "contact_id"],
            [$this->object->getUniqueLabelField()],
            -1,
            0,
            null,
            null,
            $filters_contactRel,
            "AND"
        );
        foreach ($contactElements as $contactElement) {
            CentreonContact::getInstance()->export($contactElement['contact_alias']);
            echo $this->action . $this->delim
                . "addcontact" . $this->delim
                . $contactElement[$this->object->getUniqueLabelField()] . $this->delim
                . $contactElement['contact_alias'] . "\n";
        }

        // host templates linked
        $htplRel = new Centreon_Object_Relation_Host_Template_Host($this->dependencyInjector);
        $filters_htplRel = ["h.host_register" => $this->register];
        if (!is_null($filterName)) {
            $filters_htplRel['h.host_name'] = $filterName;
        }
        $tplElements = $htplRel->getMergedParameters(
            ["host_name as host"],
            ["host_name as template", "host_id as tpl_id"],
            -1,
            0,
            "host,`order`",
            "ASC",
            $filters_htplRel,
            "AND"
        );
        foreach ($tplElements as $tplElement) {
            CentreonHostTemplate::getInstance()->export($tplElement['template']);
            echo $this->action . $this->delim
                . "addtemplate" . $this->delim
                . $tplElement['host'] . $this->delim
                . $tplElement['template'] . "\n";
        }

        // Filter only
        if (!is_null($filterName)) {
            // service templates linked
            $hostRel = new Centreon_Object_Relation_Host_Service($this->dependencyInjector);
            $helements = $hostRel->getMergedParameters(
                ["host_name"],
                ['service_description', 'service_id'],
                -1,
                0,
                null,
                null,
                ["service_register" => 0, "host_name" => $filterName],
                "AND"
            );
            foreach ($helements as $helement) {
                CentreonServiceTemplate::getInstance()->export($helement['service_description']);
            }

            // services linked
            $hostRel = new Centreon_Object_Relation_Host_Service($this->dependencyInjector);
            $helements = $hostRel->getMergedParameters(
                ["host_name"],
                ['service_description', 'service_id'],
                -1,
                0,
                null,
                null,
                ["service_register" => 1, "host_name" => $filterName],
                "AND"
            );
            foreach ($helements as $helement) {
                CentreonService::getInstance()->export($filterName . ';' . $helement['service_description']);
            }

            // service hg linked and hostgroups
            $hostRel = new Centreon_Object_Relation_Host_Group_Host($this->dependencyInjector);
            $helements = $hostRel->getMergedParameters(
                ["hg_name", "hg_id"],
                ['*'],
                -1,
                0,
                null,
                null,
                ["host_name" => $filterName],
                "AND"
            );
            foreach ($helements as $helement) {
                CentreonHostGroup::getInstance()->export($helement['hg_name']);
                CentreonHostGroupService::getInstance()->export($helement['hg_name']);
            }
        }
    }
}
