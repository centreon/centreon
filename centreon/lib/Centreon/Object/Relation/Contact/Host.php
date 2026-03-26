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
 * @class Centreon_Object_Relation_Contact_Host
 */
class Centreon_Object_Relation_Contact_Host extends Centreon_Object_Relation
{
    /** @var Centreon_Object_Contact */
    public $firstObject;

    /** @var Centreon_Object_Host */
    public $secondObject;

    /** @var string */
    protected $relationTable = 'contact_host_relation';

    /** @var string */
    protected $firstKey = 'contact_id';

    /** @var string */
    protected $secondKey = 'host_host_id';

    /**
     * Centreon_Object_Relation_Contact_Host constructor
     *
     * @param Pimple\Container $dependencyInjector
     */
    public function __construct(Pimple\Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->firstObject = new Centreon_Object_Contact($dependencyInjector);
        $this->secondObject = new Centreon_Object_Host($dependencyInjector);
    }
}
