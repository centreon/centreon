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

require_once __DIR__ . '/../Params.class.php';

/**
 * Class
 *
 * @class CentreonWidgetParamsCompare
 */
class CentreonWidgetParamsCompare extends CentreonWidgetParams
{
    /** @var HTML_QuickForm_element */
    public $element;

    /**
     * CentreonWidgetParamsCompare Constructor
     *
     * @param CentreonDB $db
     * @param HTML_Quickform $quickform
     * @param int $userId
     *
     * @throws PDOException
     */
    public function __construct($db, $quickform, $userId)
    {
        parent::__construct($db, $quickform, $userId);
    }

    /**
     * @param $params
     *
     * @throws HTML_QuickForm_Error
     * @return void
     */
    public function init($params): void
    {
        parent::init($params);
        if (isset($this->quickform)) {
            $elems = [];
            $operands = [null => null, 'gt' => '>', 'lt' => '<', 'gte' => '>=', 'lte' => '<=', 'eq' => '=', 'ne' => '!=', 'like' => 'LIKE', 'notlike' => 'NOT LIKE', 'regex' => 'REGEXP', 'notregex' => 'NOT REGEXP'];
            $elems[] = $this->quickform->addElement('select', 'op_' . $params['parameter_id'], '', $operands);
            $elems[] = $this->quickform->addElement(
                'text',
                'cmp_' . $params['parameter_id'],
                $params['parameter_name'],
                ['size' => 30]
            );
            $this->element = $this->quickform->addGroup(
                $elems,
                'param_' . $params['parameter_id'],
                $params['parameter_name'],
                '&nbsp;'
            );
        }
    }

    /**
     * @param $params
     *
     * @throws HTML_QuickForm_Error
     * @throws PDOException
     * @return void
     */
    public function setValue($params): void
    {
        $userPref = $this->getUserPreferences($params);
        if (isset($userPref)) {
            $target = $userPref;
        } elseif (isset($params['default_value']) && $params['default_value'] != '') {
            $target = $params['default_value'];
        }
        if (isset($target)) {
            if (preg_match('/(gt |lt |gte |lte |eq |ne |like |notlike |regex |notregex )(.+)/', $target, $matches)) {
                $op = trim($matches[1]);
                $val = trim($matches[2]);
            }
            if (isset($op, $val)) {
                $this->quickform->setDefaults(['op_' . $params['parameter_id'] => $op, 'cmp_' . $params['parameter_id'] => $val]);
            }
        }
    }
}
