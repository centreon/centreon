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
namespace Centreon\Test\Mock;

/**
 * Mock class for PEAR
 *
 * @author Centreon
 * @version 1.0.0
 * @package centreon-license-manager
 * @subpackage test
 */
class PEAR
{
    /**
     * Test if it's a PEAR Error
     *
     * @return bool It's in error
     */
    static public function isError($obj)
    {
        return false;
    }
}