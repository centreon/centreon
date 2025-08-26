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

class CentreonCommand extends BaseObject
{
    public function getCommandIdByName($commandName)
    {
        switch ($commandName) {
            // A existant command, need for test update/insert command
            case 'check_centreon_ping':
                return NULL;
        }
        return $this->getIncrementedId();
    }

    public function insert($parameters): void
    {
        $this->incrementalId++;
    }
    
    public function update($command_id, $command)
    {
        ;
    }

    public function deleteCommandByName($command_name)
    {
        ;
    }

    public function getLinkedHostsbyName()
    {

    }

    public function getLinkedServicesbyName()
    {

    }
}
