<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace CentreonLegacy\Core\Widget;

class Remover extends Widget
{
    /**
     * @return bool
     */
    public function remove()
    {
        $query = 'DELETE FROM widget_models '
            . 'WHERE directory = :directory '
            . 'AND is_internal = FALSE ';

        $sth = $this->services->get('configuration_db')->prepare($query);

        $sth->bindParam(':directory', $this->widgetName, \PDO::PARAM_STR);

        $removed = $sth->execute() ? true : false;

        return $removed;
    }
}
