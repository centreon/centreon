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

namespace Centreon\Tests\Infrastructure\CentreonLegacyDB;

use Centreon\Infrastructure\CentreonLegacyDB\StatementCollector;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class StatementCollectorTest extends TestCase
{
    public function testBind(): void
    {
        $value = '...';
        $key = 'key';
        $dataType = PDO::PARAM_STR;

        $callback = function ($_key, $_value, $_dataType) use ($key, $value, $dataType) {
            $this->assertEquals($key, $_key);
            $this->assertEquals($value, $_value);
            $this->assertEquals($dataType, $_dataType);

            return true;
        };

        $collector = new StatementCollector();
        $collector->addColumn($key, $value, $dataType);
        $collector->addValue($key, $value, $dataType);
        $collector->addParam($key, $value, $dataType);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('bindColumn')
            ->will($this->returnCallback($callback));
        $stmt->method('bindValue')
            ->will($this->returnCallback($callback));
        $stmt->method('bindParam')
            ->will($this->returnCallback($callback));

        $collector->bind($stmt);
    }
}
