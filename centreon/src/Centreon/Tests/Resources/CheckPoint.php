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

namespace Centreon\Tests\Resources;

use PHPUnit\Framework\TestCase;

/**
 * This class help to test list of callbacks or methods if they were executed
 *
 * <example>
 * class MyTest extends TestCase
 * {
 *      public testMethod()
 *      {
 *          $checkpoint = (new CheckPoint)
 *              ->add('point1');
 *      }
 * }
 * </example>
 */
class CheckPoint
{
    /** @var array */
    protected $points;

    /**
     * Add point
     *
     * @param string $name
     * @return self
     */
    public function add($name): self
    {
        $this->points[$name] = false;

        return $this;
    }

    /**
     * Mark point as executed
     *
     * @param $name
     * @return void
     */
    public function mark($name): void
    {
        $this->points[$name] = true;
    }

    /**
     * Check list of points
     *
     * @param TestCase $testCase
     */
    public function assert(TestCase $testCase): void
    {
        $expected = [];

        foreach ($this->points as $key => $val) {
            $expected[$key] = true;
        }

        $testCase->assertEquals($expected, $this->points);
    }
}
