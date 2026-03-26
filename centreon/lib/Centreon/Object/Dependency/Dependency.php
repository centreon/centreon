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

require_once 'Centreon/Object/Object.php';

/**
 * Class
 *
 * @class Centreon_Object_Dependency
 */
class Centreon_Object_Dependency extends Centreon_Object
{
    /** @var string */
    protected $table = 'dependency';

    /** @var string */
    protected $primaryKey = 'dep_id';

    /** @var string */
    protected $uniqueLabelField = 'dep_name';
}
