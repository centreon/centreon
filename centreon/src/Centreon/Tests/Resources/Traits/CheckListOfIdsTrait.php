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

namespace Centreon\Tests\Resources\Traits;

trait CheckListOfIdsTrait
{
    public function checkListOfIdsTrait(
        string $repositoryClass,
        $method,
        $tableName = null,
        $identificator = null,
    ): void {
        $ids = [1, 3, 5];

        $repository = $this->createPartialMock(
            $repositoryClass,
            [
                'checkListOfIdsTrait',
            ]
        );
        $repository->method('checkListOfIdsTrait')
            ->will($this->returnCallback(function () use ($ids, $tableName, $identificator) {
                $this->assertEquals([
                    $ids,
                    $tableName,
                    $identificator,
                ], func_get_args());

                return true;
            }));

        $this->assertTrue($repository->{$method}($ids));
    }
}
