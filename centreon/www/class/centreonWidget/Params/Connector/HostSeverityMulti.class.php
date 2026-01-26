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
 * Creation of a new connector for the host severity that use the centreonHostcategories object
 * with configuration from centreon_configuration_host_severity file
 */

/**
 * Class
 *
 * @class CentreonWidgetParamsConnectorHostSeverityMulti
 */
class CentreonWidgetParamsConnectorHostSeverityMulti extends CentreonWidgetParamsSelect2
{
    /**
     * @return array
     */
    public function getParameters()
    {
        $path = './api/internal.php?object=centreon_configuration_hostcategory&action=list&t=s';

        return ['datasourceOrigin' => 'ajax', 'availableDatasetRoute' => $path, 'multiple' => true, 'linkedObject' => 'centreonHostcategories'];
    }
}
