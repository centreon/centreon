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
 * Abstract class for mock
 *
 * @author Centreon
 * @version 1.0.0
 * @package centreon-test-lib
 * @subpackage test
 */
abstract class Mock
{
    protected $incrementalId = 1;
    private static $countFunction = array();

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Return real result
     *
     * @param any $expectedResult the original result
     * @return $result
     */
    protected function realResult($expectedResult, $stackTraceId = 1)
    {
        global $customResult;
        if (!isset($customResult) || !is_array($customResult)) {
            return $expectedResult;
        }

        $stacktrace = debug_backtrace();
        $calledMethod = $stacktrace[$stackTraceId]['function'];

        if (isset(self::$countFunction[$calledMethod])) {
            self::$countFunction[$calledMethod]++;
        } else {
            self::$countFunction[$calledMethod] = 0;
        }

        $result = $expectedResult;

        if (array_key_exists($calledMethod, $customResult)) {

            if (!is_array($customResult[$calledMethod]) ||
                (!array_key_exists('any', $customResult[$calledMethod]) && !array_key_exists('at', $customResult[$calledMethod]))
                ) {

                $result = $customResult[$calledMethod];

            } else if (isset($customResult[$calledMethod]['at']) &&
                array_key_exists(self::$countFunction[$calledMethod], $customResult[$calledMethod]['at'])) {

                $result = $customResult[$calledMethod]['at'][self::$countFunction[$calledMethod]];

            } else if (array_key_exists('any', $customResult[$calledMethod])) {

                $result = $customResult[$calledMethod]['any'];

            }
        }

        return $result;
    }    
}
