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
 * @class ServiceGroupRelation
 * @package ConfigGenerateRemote\Relations
 */
class ServiceGroupRelation extends AbstractObject
{
    protected $table = 'servicegroup_relation';
    protected $generateFilename = 'servicegroup_relation.infile';
    protected $attributesWrite = [
        'host_host_id',
        'service_service_id',
        'servicegroup_sg_id',
    ];

    /**
     * Add relation
     *
     * @param int $sgId
     * @param int $hostId
     * @param int $serviceId
     *
     * @return void
     * @throws Exception
     */
    public function addRelationHostService(int $sgId, int $hostId, int $serviceId)
    {
        if ($this->checkGenerate($sgId . '.' . $hostId . '.' . $serviceId)) {
            return null;
        }
        $relation = [
            'servicegroup_sg_id' => $sgId,
            'host_host_id' => $hostId,
            'service_service_id' => $serviceId,
        ];
        $this->generateObjectInFile($relation, $sgId . '.' . $hostId . '.' . $serviceId);
    }
}
