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

/**
 * Class
 *
 * @class MetaTimeperiod
 */
class MetaTimeperiod extends AbstractObject
{
    /** @var string */
    protected $generate_filename = 'meta_timeperiod.cfg';

    /** @var string */
    protected string $object_name = 'timeperiod';

    /** @var string[] */
    protected $attributes_write = ['timeperiod_name', 'alias', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    /**
     * @throws Exception
     * @return int|void
     */
    public function generateObjects()
    {
        if ($this->checkGenerate(0)) {
            return 0;
        }

        $object = [];
        $object['timeperiod_name'] = 'meta_timeperiod';
        $object['alias'] = 'meta_timeperiod';
        $object['sunday'] = '00:00-24:00';
        $object['monday'] = '00:00-24:00';
        $object['tuesday'] = '00:00-24:00';
        $object['wednesday'] = '00:00-24:00';
        $object['thursday'] = '00:00-24:00';
        $object['friday'] = '00:00-24:00';
        $object['saturday'] = '00:00-24:00';
        $this->generateObjectInFile($object, 0);
    }
}
