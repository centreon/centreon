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

require_once __DIR__ . '/../Select2.class.php';

/**
 * Class
 *
 * @class CentreonWidgetParamsConnectorHostMulti
 */
class CentreonWidgetParamsConnectorHostMulti extends CentreonWidgetParamsSelect2
{
    /**
     * CentreonWidgetParamsConnectorHostMulti constructor
     *
     * @param $db
     * @param $quickform
     * @param $userId
     *
     * @throws PDOException
     */
    public function __construct($db, $quickform, $userId)
    {
        parent::__construct($db, $quickform, $userId);
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        $path = './include/common/webServices/rest/internal.php?object=centreon_configuration_host&action=list';

        return ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $path, 'multiple' => true, 'linkedObject' => 'centreonHost'];
    }
}
