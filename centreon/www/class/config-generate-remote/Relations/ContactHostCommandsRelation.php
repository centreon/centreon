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
 * @class ContactHostCommandsRelation
 * @package ConfigGenerateRemote\Relations
 */
class ContactHostCommandsRelation extends AbstractObject
{
    /** @var string */
    protected $table = 'contact_hostcommands_relation';
    /** @var string */
    protected $generateFilename = 'contact_hostcommands_relation.infile';
    /** @var string[] */
    protected $attributesWrite = [
        'contact_contact_id',
        'command_command_id',
    ];

    /**
     * Add relaton
     *
     * @param int $contactId
     * @param int $cmdId
     *
     * @return void
     * @throws Exception
     */
    public function addRelation(int $contactId, int $cmdId): void
    {
        $relation = [
            'contact_contact_id' => $contactId,
            'command_command_id' => $cmdId,
        ];
        $this->generateObjectInFile($relation);
    }
}
