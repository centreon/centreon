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
 * @class HostTemplateRelation
 * @package ConfigGenerateRemote\Relations
 */
class HostTemplateRelation extends AbstractObject
{
    /** @var string */
    protected $table = 'host_template_relation';
    /** @var string */
    protected $generateFilename = 'host_template_relation.infile';
    /** @var string[] */
    protected $attributesWrite = [
        'host_host_id',
        'host_tpl_id',
        'order',
    ];

    /**
     * Add relation
     *
     * @param int $hostId
     * @param int $hostTplId
     * @param int $order
     *
     * @return void
     * @throws Exception
     */
    public function addRelation(int $hostId, int $hostTplId, $order): void
    {
        $relation = [
            'host_host_id' => $hostId,
            'host_tpl_id' => $hostTplId,
            'order' => $order,
        ];
        $this->generateObjectInFile($relation, $hostId . '.' . $hostTplId . '.' . $order);
    }
}
