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
 * @class CentreonWidgetParamsDate
 */
class CentreonWidgetParamsDate extends CentreonWidgetParams
{
    /** @var HTML_QuickForm_Element */
    public $element;

    /**
     * CentreonWidgetParamsDate Constructor
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
            $elems[] = $this->quickform->addElement(
                'text',
                'from_' . $params['parameter_id'],
                _('From'),
                ['size' => 10, 'class' => 'datepicker']
            );
            $elems[] = $this->quickform->addElement(
                'text',
                'to_' . $params['parameter_id'],
                _('To'),
                ['size' => 10, 'class' => 'datepicker']
            );
            $this->element = $this->quickform->addGroup(
                $elems,
                'param_' . $params['parameter_id'],
                $params['parameter_name'],
                '&nbsp;to&nbsp;'
            );
        }
    }

    /**
     * @param $params
     *
     * @throws CentreonWidgetParamsException
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
            $tab = explode(',', $target);
            if (! isset($tab[0]) || ! isset($tab[1])) {
                throw new CentreonWidgetParamsException('Incorrect date format found in database');
            }
            $this->quickform->setDefaults(['from_' . $params['parameter_id'] => $tab[0], 'to_' . $params['parameter_id'] => $tab[1]]);
        }
    }
}
