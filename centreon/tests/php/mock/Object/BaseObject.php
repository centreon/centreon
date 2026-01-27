<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Centreon\Test\Mock\Object;

class BaseObject
{
    protected int $incrementalId = 1;

    private static array $countFunction = [];

    protected function realResult($expectedResult)
    {
        global $customResult;

        $stacktrace = debug_backtrace();
        $calledMethod = $stacktrace[2]['function'];

        if (isset(self::$countFunction[$calledMethod])) {
            self::$countFunction[$calledMethod]++;
        } else {
            self::$countFunction[$calledMethod] = 0;
        }

        $result = $expectedResult;

        if (array_key_exists($calledMethod, $customResult)) {

            if (! is_array($customResult[$calledMethod])) {
                $result = $customResult[$calledMethod];
            } elseif (isset($customResult[$calledMethod]['at'])
                && array_key_exists(self::$countFunction[$calledMethod], $customResult[$calledMethod]['at'])) {
                $result = $customResult[$calledMethod]['at'][self::$countFunction[$calledMethod]];
            } elseif (array_key_exists('any', $customResult[$calledMethod])) {
                $result = $customResult[$calledMethod]['any'];
            }
        }

        return $result;
    }

    protected function getIncrementedId()
    {
        return $this->realResult($this->incrementalId++);
    }
}
