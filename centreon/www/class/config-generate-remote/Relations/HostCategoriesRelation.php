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
 * @class HostCategoriesRelation
 * @package ConfigGenerateRemote\Relations
 */
class HostCategoriesRelation extends AbstractObject
{
    protected $table = 'hostcategories_relation';
    protected $generateFilename = 'hostcategories_relation.infile';
    protected $attributesWrite = [
        'hostcategories_hc_id',
        'host_host_id',
    ];

    /**
     * Add relation
     *
     * @param int $hcId
     * @param int $hostId
     *
     * @return void
     * @throws Exception
     */
    public function addRelation(int $hcId, int $hostId): void
    {
        $relation = [
            'hostcategories_hc_id' => $hcId,
            'host_host_id' => $hostId,
        ];
        $this->generateObjectInFile($relation);
    }
}
