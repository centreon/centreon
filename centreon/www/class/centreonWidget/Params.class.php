<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

require_once __DIR__ . '/Params/Interface.class.php';

/**
 * Class
 *
 * @class CentreonWidgetParamsException
 */
class CentreonWidgetParamsException extends Exception
{
}

/**
 * Class
 *
 * @class CentreonWidgetParams
 */
abstract class CentreonWidgetParams implements CentreonWidgetParamsInterface
{
    /** @var */
    protected static $instances;

    /** @var int */
    public $userId;

    /** @var mixed */
    public $element;

    /** @var CentreonDB */
    protected $db;

    /** @var HTML_Quickform */
    protected $quickform;

    /** @var mixed */
    protected $params;

    /** @var array */
    protected $userGroups = [];

    /** @var false */
    protected $trigger = false;

    /** @var CentreonACL */
    protected $acl;

    /** @var CentreonDB */
    protected $monitoringDb;

    /** @var string[] */
    protected $multiType = ['serviceMulti'];

    /**
     * Constructor
     *
     * @param CentreonDB $db
     * @param HTML_Quickform $quickform
     * @param int $userId
     *
     * @throws PDOException
     */
    public function __construct($db, $quickform, $userId)
    {
        $this->db = $db;
        $this->quickform = $quickform;
        $this->userId = $userId;
        $query = 'SELECT contactgroup_cg_id
                          FROM contactgroup_contact_relation
                          WHERE contact_contact_id = ' . $this->db->escape($this->userId);
        $res = $this->db->query($query);
        while ($row = $res->fetchRow()) {
            $this->userGroups[$row['contactgroup_cg_id']] = $row['contactgroup_cg_id'];
        }
        $this->acl = new CentreonACL($userId);
        $this->monitoringDb = new CentreonDB('centstorage');
    }

    /**
     * Get User Preferences
     *
     * @param array $params
     *
     * @throws PDOException
     * @return mixed
     */
    protected function getUserPreferences($params)
    {
        $query = 'SELECT preference_value
                  FROM widget_preferences wp, widget_views wv, custom_view_user_relation cvur
                  WHERE wp.parameter_id = ' . $this->db->escape($params['parameter_id']) . '
                  AND wp.widget_view_id = wv.widget_view_id
                  AND wv.widget_id = ' . $this->db->escape($params['widget_id']) . '
                  AND wv.custom_view_id = cvur.custom_view_id
                  AND wp.user_id = ' . $this->db->escape($this->userId) . '
                  AND (cvur.user_id = wp.user_id';
        if (count($this->userGroups)) {
            $cglist = implode(',', $this->userGroups);
            $query .= " OR cvur.usergroup_id IN ({$cglist}) ";
        }
        $query .= ') AND cvur.custom_view_id = ' . $this->db->escape($params['custom_view_id']) . '
                                  LIMIT 1';
        $res = $this->db->query($query);
        if ($res->rowCount()) {
            $row = $res->fetchRow();

            return $row['preference_value'];
        }

        return null;
    }

    /**
     * Factory
     *
     * @param CentreonDB $db
     * @param HTML_Quickform $quickform
     * @param $className
     * @param int $userId
     *
     * @return mixed
     */
    public static function factory($db, $quickform, $className, $userId)
    {
        if (! isset(self::$instances[$className])) {
            self::$instances[$className] = new $className($db, $quickform, $userId);
        }

        return self::$instances[$className];
    }

    /**
     * Init
     *
     * @param array $params
     * @return void
     */
    public function init($params): void
    {
        $this->params = $params;
    }

    /**
     * Set Value
     *
     * @param array $params
     *
     * @throws HTML_QuickForm_Error
     * @throws PDOException
     * @return void
     */
    public function setValue($params): void
    {
        $userPref = $this->getUserPreferences($params);
        if (in_array($params['ft_typename'], $this->multiType)) {
            if (is_string($userPref) && strpos($userPref, ',') > -1) {
                $userPref = explode(',', $userPref);
            }
        }
        if (isset($userPref)) {
            $this->quickform->setDefaults(['param_' . $params['parameter_id'] => $userPref]);
        } elseif (isset($params['default_value']) && $params['default_value'] != '') {
            $this->quickform->setDefaults(['param_' . $params['parameter_id'] => $params['default_value']]);
        }
    }

    /**
     * Get Element
     *
     * @return HTML_Quickform
     */
    public function getElement()
    {
        return $this->element;
    }

    /**
     * Get List Values
     *
     * @param int $paramId
     *
     * @throws PDOException
     * @return array
     */
    public function getListValues($paramId)
    {
        $query = 'SELECT option_name, option_value
                          FROM widget_parameters_multiple_options
                          WHERE parameter_id = ' . $this->db->escape($paramId);
        $res = $this->db->query($query);
        $tab = [null => null];
        while ($row = $res->fetchRow()) {
            $tab[$row['option_value']] = $row['option_name'];
        }

        return $tab;
    }

    /**
     * @return false
     */
    public function getTrigger()
    {
        return $this->trigger;
    }
}
