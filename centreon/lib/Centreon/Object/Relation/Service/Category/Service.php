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

require_once 'Centreon/Object/Relation/Relation.php';
require_once 'Centreon/Object/Service/Category.php';
require_once 'Centreon/Object/Service/Service.php';

/**
 * Class
 *
 * @class Centreon_Object_Relation_Service_Category_Service
 */
class Centreon_Object_Relation_Service_Category_Service extends Centreon_Object_Relation
{
    /** @var Centreon_Object_Service_Category */
    public $firstObject;

    /** @var Centreon_Object_Service */
    public $secondObject;

    /** @var string */
    protected $relationTable = 'service_categories_relation';

    /** @var string */
    protected $firstKey = 'sc_id';

    /** @var string */
    protected $secondKey = 'service_service_id';

    /**
     * Centreon_Object_Relation_Service_Category_Service constructor
     *
     * @param Pimple\Container $dependencyInjector
     */
    public function __construct(Pimple\Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->firstObject = new Centreon_Object_Service_Category($dependencyInjector);
        $this->secondObject = new Centreon_Object_Service($dependencyInjector);
    }
}
