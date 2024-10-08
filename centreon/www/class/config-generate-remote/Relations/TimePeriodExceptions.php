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
 * @class TimePeriodExceptions
 * @package ConfigGenerateRemote\Relations
 */
class TimePeriodExceptions extends AbstractObject
{
    /** @var string */
    protected $table = 'timeperiod_exceptions';
    /** @var string */
    protected $generateFilename = 'timeperiod_exceptions.infile';
    /** @var string[] */
    protected $attributesWrite = [
        'timeperiod_id',
        'days',
        'timerange',
    ];

    /**
     * Add relation
     *
     * @param array $object
     * @param int $tpId
     *
     * @return void
     * @throws Exception
     */
    public function add(array $object, int $tpId): void
    {
        $this->generateObjectInFile($object, $tpId);
    }
}
