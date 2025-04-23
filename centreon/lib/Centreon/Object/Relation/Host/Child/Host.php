<?php

/*
 * Copyright 2016-2020 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets
 * the needs in IT infrastructure and application monitoring for
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once "Centreon/Object/Relation/Relation.php";

/**
 * Class
 *
 * @class Centreon_Object_Relation_Host_Child_Host
 */
class Centreon_Object_Relation_Host_Child_Host extends Centreon_Object_Relation
{

    /** @var Centreon_Object_Host */
    public $firstObject;
    /** @var Centreon_Object_Host */
    public $secondObject;
    /** @var string */
    protected $relationTable = "host_hostparent_relation";
    /** @var string */
    protected $firstKey = "host_host_id";
    /** @var string */
    protected $secondKey = "host_parent_hp_id";

    /**
     * Centreon_Object_Relation_Host_Child_Host constructor
     *
     * @param \Pimple\Container $dependencyInjector
     */
    public function __construct(\Pimple\Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->firstObject = new Centreon_Object_Host($dependencyInjector);
        $this->secondObject = new Centreon_Object_Host($dependencyInjector);
    }
}
