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

/**
 * Class
 *
 * @class Centreon_Object_Relation_Trap_Service
 */
class Centreon_Object_Relation_Trap_Service extends Centreon_Object_Relation
{
    /** @var Centreon_Object_Trap */
    public $firstObject;

    /** @var Centreon_Object_Service */
    public $secondObject;

    /** @var string */
    protected $relationTable = 'traps_service_relation';

    /** @var string */
    protected $firstKey = 'traps_id';

    /** @var string */
    protected $secondKey = 'service_id';

    /**
     * Centreon_Object_Relation_Trap_Service constructor
     *
     * @param Pimple\Container $dependencyInjector
     */
    public function __construct(Pimple\Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->firstObject = new Centreon_Object_Trap($dependencyInjector);
        $this->secondObject = new Centreon_Object_Service($dependencyInjector);
    }
}
