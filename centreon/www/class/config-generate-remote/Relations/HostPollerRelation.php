<?php
/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
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
 *
 * For more information : contact@centreon.com
 *
 */

namespace ConfigGenerateRemote\Relations;

use ConfigGenerateRemote\Abstracts\AbstractObject;
use Exception;

/**
 * Class
 *
 * @class HostPollerRelation
 * @package ConfigGenerateRemote\Relations
 */
class HostPollerRelation extends AbstractObject
{
    protected $table = 'ns_host_relation';
    protected $generateFilename = 'ns_host_relation.infile';
    protected $attributesWrite = [
        'nagios_server_id',
        'host_host_id',
    ];

    /**
     * Add relation between host and poller
     *
     * @param int $pollerId
     * @param int $hostId
     *
     * @return void
     * @throws Exception
     */
    public function addRelation(int $pollerId, int $hostId): void
    {
        $relation = [
            'nagios_server_id' => $pollerId,
            'host_host_id' => $hostId,
        ];
        $this->generateObjectInFile($relation, $hostId . '.' . $serviceId);
    }
}
