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

declare(strict_types=1);

namespace Tests\www\class\ConfigGenerate;

use AbstractObject;
use Host;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

require_once __DIR__ . '/../../../../../www/class/config-generate/host.class.php';

final class HostTest extends TestCase
{
    public function testItWritesEachAttributeOfAHostOnASingleLine(): void
    {
        // The constructor boots the kernel and the backend, which writing an object does not need.
        $host = (new ReflectionClass(Host::class))->newInstanceWithoutConstructor();
        $file = fopen('php://memory', 'w+');
        (new ReflectionProperty(AbstractObject::class, 'fp'))->setValue($host, $file);

        (new ReflectionMethod(Host::class, 'writeObject'))->invoke($host, [
            'host_name' => 'my-host',
            'alias' => "first line\r\nsecond line",
            'notes' => "first line\nsecond line",
            'macros' => ["_CUSTOM\nNAME" => "first line\rsecond line"],
        ]);

        rewind($file);
        $written = (string) stream_get_contents($file);

        self::assertSame(
            "\ndefine host {\n"
            . sprintf("    %-30s %s \n", 'host_name', 'my-host')
            . sprintf("    %-30s %s \n", 'alias', 'first line second line')
            . sprintf("    %-30s %s \n", 'notes', 'first line second line')
            . sprintf("    %-30s %s \n", '_CUSTOM NAME', 'first line second line')
            . "}\n",
            $written
        );
    }
}
