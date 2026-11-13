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

namespace Tests\App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Application\Command\CreateServiceCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Logging\Attribute\SensitivityScanner;
use App\Shared\Infrastructure\Logging\LogPayloadNormalizer;
use App\Shared\Infrastructure\Logging\PayloadSanitizer;
use App\Shared\Infrastructure\Logging\SensitiveKeywordDenylist;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;

/**
 * `LoggingMiddleware` logs the command payload on every dispatch, and command arguments and password macros may carry
 * a password: the vaulting only happens later, in the handler.
 */
final class CreateServiceCommandLoggingTest extends TestCase
{
    public function testTheCommandArgumentsNeverReachTheLogs(): void
    {
        SensitivityScanner::reset();
        SensitiveKeywordDenylist::reset();

        $command = new CreateServiceCommand(
            name: new ServiceName('ping'),
            hostId: new HostId(10),
            actor: TestActingUsers::unrestricted(1),
            checkCommandId: new CommandId(1),
            checkCommandArgs: ['--secret=check-secret'],
            dataProcessing: new ServiceDataProcessing(eventHandlerCommandId: new CommandId(2), eventHandlerArgs: ['--secret=handler-secret']),
        );

        $payload = new LogPayloadNormalizer(new CamelCaseToSnakeCaseNameConverter())->normalize($command);
        $sanitised = new PayloadSanitizer()->sanitize($payload, CreateServiceCommand::class);

        self::assertIsArray($sanitised);
        self::assertSame('***', $sanitised['check_command_args']);
        $logged = (string) json_encode($sanitised);
        self::assertStringNotContainsString('check-secret', $logged);
        self::assertStringNotContainsString('handler-secret', $logged);
        // The rest of the payload still goes through, so the masking is targeted.
        self::assertArrayHasKey('name', $sanitised);
    }

    public function testTheMacroChangesNeverReachTheLogs(): void
    {
        SensitivityScanner::reset();
        SensitiveKeywordDenylist::reset();

        $command = new CreateServiceCommand(
            name: new ServiceName('ping'),
            hostId: new HostId(10),
            actor: TestActingUsers::unrestricted(1),
            macroChanges: new Collection([
                new ServiceMacroChange(new ServiceMacroName('token'), 'top-secret', isPassword: true),
            ], ServiceMacroChange::class),
        );

        $payload = new LogPayloadNormalizer(new CamelCaseToSnakeCaseNameConverter())->normalize($command);
        $sanitised = new PayloadSanitizer()->sanitize($payload, CreateServiceCommand::class);

        self::assertIsArray($sanitised);
        self::assertSame('***', $sanitised['macro_changes']);
        self::assertStringNotContainsString('top-secret', (string) json_encode($sanitised));
    }
}
