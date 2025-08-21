<?php
/**
 * Copyright 2016 Centreon
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace Centreon\Test\Mock\Object;

class CentreonTimeperiod extends BaseObject
{
    public function getTimperiodIdByName($tp_name)
    {
        return $this->getIncrementedId();
    }

    public function update($tp_id, $timeperiod)
    {
        ;
    }

    public function deleteTimeperiodByName($tp_name)
    {
        ;
    }

    public function setTimeperiodException($tp_id, $exceptions)
    {
        ;
    }

    public function deleteTimeperiodException($tp_id)
    {
        ;
    }

    public function deleteTimeperiodInclude($tp_id)
    {
        ;
    }

    public function setTimeperiodDependency($tp_id, $dependencies)
    {
        ;
    }

    public function getLinkedHostsByName()
    {
        return array();
    }

    public function getLinkedServicesByName()
    {
        return array();
    }

    public function getLinkedContactsByName()
    {
        return array();
    }

    public function getLinkedTimeperiodsByName()
    {
        return array();
    }
}
