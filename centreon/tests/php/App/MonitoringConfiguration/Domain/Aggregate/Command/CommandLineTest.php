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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Command;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use PHPUnit\Framework\TestCase;

final class CommandLineTest extends TestCase
{
    public function testExtractArguments(): void
    {
        $commandLine = new CommandLine('/usr/lib/nagios/plugins/check_ping -H $HOSTADDRESS$ -w $ARG1$ -c $ARG2$');

        self::assertSame(['ARG1', 'ARG2'], $commandLine->extractArguments());
    }

    public function testExtractArgumentsWithDuplicates(): void
    {
        $commandLine = new CommandLine('/check -w $ARG1$ -c $ARG1$');

        self::assertSame(['ARG1'], $commandLine->extractArguments());
    }

    public function testExtractArgumentsEmpty(): void
    {
        $commandLine = new CommandLine('/usr/lib/nagios/plugins/check_ping -H $HOSTADDRESS$');

        self::assertSame([], $commandLine->extractArguments());
    }

    public function testExtractHostMacros(): void
    {
        $commandLine = new CommandLine('/check -H $_HOSTSNMP_COMMUNITY$ -p $_HOSTSNMP_PORT$');

        self::assertSame(['SNMP_COMMUNITY', 'SNMP_PORT'], $commandLine->extractHostMacros());
    }

    public function testExtractHostMacrosWithDuplicates(): void
    {
        $commandLine = new CommandLine('/check -H $_HOSTSNMP_COMMUNITY$ -c $_HOSTSNMP_COMMUNITY$');

        self::assertSame(['SNMP_COMMUNITY'], $commandLine->extractHostMacros());
    }

    public function testExtractHostMacrosEmpty(): void
    {
        $commandLine = new CommandLine('/usr/lib/nagios/plugins/check_ping');

        self::assertSame([], $commandLine->extractHostMacros());
    }

    public function testExtractServiceMacros(): void
    {
        $commandLine = new CommandLine('/check -w $_SERVICEWARNING$ -c $_SERVICECRITICAL$');

        self::assertSame(['WARNING', 'CRITICAL'], $commandLine->extractServiceMacros());
    }

    public function testExtractServiceMacrosWithDuplicates(): void
    {
        $commandLine = new CommandLine('/check -w $_SERVICEWARNING$ -c $_SERVICEWARNING$');

        self::assertSame(['WARNING'], $commandLine->extractServiceMacros());
    }

    public function testExtractServiceMacrosEmpty(): void
    {
        $commandLine = new CommandLine('/usr/lib/nagios/plugins/check_ping');

        self::assertSame([], $commandLine->extractServiceMacros());
    }

    public function testExtractMixedMacrosAndArgs(): void
    {
        $commandLine = new CommandLine('/check -H $_HOSTSNMP_COMMUNITY$ -w $ARG1$ -c $ARG2$ -p $_SERVICEPORT$');

        self::assertSame(['ARG1', 'ARG2'], $commandLine->extractArguments());
        self::assertSame(['SNMP_COMMUNITY'], $commandLine->extractHostMacros());
        self::assertSame(['PORT'], $commandLine->extractServiceMacros());
    }
}
