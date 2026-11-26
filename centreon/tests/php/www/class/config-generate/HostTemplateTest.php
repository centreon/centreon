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

use HostTemplate;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Severity;

class HostTemplateTest extends TestCase
{
    private HostTemplate $hostTemplate;

    protected function setUp(): void
    {
        // Host template 10 has severity 5 (level 3), host template 11 has none.
        // The constructor boots the kernel and the database, which the severity cache does not need.
        $severity = (new ReflectionClass(Severity::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(Severity::class, 'done_cache'))->setValue($severity, 1);
        (new ReflectionProperty(Severity::class, 'host_linked_cache'))->setValue($severity, [10 => 5]);
        (new ReflectionProperty(Severity::class, 'host_severity_cache'))->setValue(
            $severity,
            [5 => ['hc_id' => 5, 'hc_name' => 'severity-level-3', 'level' => 3]]
        );

        $this->hostTemplate = new class ($severity) extends HostTemplate {
            public function __construct(private readonly Severity $severity)
            {
            }

            protected function getSeverityInstance(): Severity
            {
                return $this->severity;
            }
        };
    }

    public function testSeverityMacrosAreExportedForEveryPoller(): void
    {
        // State left by the export of a previous poller: the severity is cached
        // and the macros have just been rebuilt for the current poller.
        $this->hostTemplate->hosts = [
            10 => ['severity_id' => 5, 'macros' => ['_HOST_ID' => 10]],
        ];

        $this->getSeverity(10);

        $this->assertSame(
            [
                '_HOST_ID' => 10,
                '_CRITICALITY_LEVEL' => 3,
                '_CRITICALITY_ID' => 5,
                'severity' => 5,
            ],
            $this->hostTemplate->hosts[10]['macros']
        );
    }

    public function testNoSeverityMacroIsAddedWhenTemplateHasNoSeverity(): void
    {
        $this->hostTemplate->hosts = [
            11 => ['macros' => ['_HOST_ID' => 11]],
        ];

        $this->getSeverity(11);

        $this->assertSame(['_HOST_ID' => 11], $this->hostTemplate->hosts[11]['macros']);
    }

    private function getSeverity(int $hostId): void
    {
        (new ReflectionMethod(HostTemplate::class, 'getSeverity'))->invoke($this->hostTemplate, $hostId);
    }
}
