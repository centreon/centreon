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
 * @class CentreonWidgetParamsList
 */
class CentreonWidgetParamsList extends CentreonWidgetParams
{
    /** @var HTML_QuickForm_Element */
    public $element;

    /**
     * CentreonWidgetParamsList Constructor
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
     * @throws HTML_QuickForm_Error|PDOException
     * @return void
     */
    public function init($params): void
    {
        parent::init($params);
        if (isset($this->quickform)) {
            $tab = $this->getListValues($params['parameter_id']);
            $this->element = $this->quickform->addElement(
                'select',
                'param_' . $params['parameter_id'],
                $params['parameter_name'],
                $tab
            );
        }
    }
}
