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

use App\MonitoringConfiguration\Application\Command\PatchServiceCommand;
use App\MonitoringConfiguration\Application\Command\ServiceMacroChanges;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\Shared\Infrastructure\Logging\Attribute\SensitivityScanner;
use App\Shared\Infrastructure\Logging\LogPayloadNormalizer;
use App\Shared\Infrastructure\Logging\PayloadSanitizer;
use App\Shared\Infrastructure\Logging\SensitiveKeywordDenylist;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;

/**
 * `LoggingMiddleware` logs the command payload on every dispatch, and a password macro travels on the command in
 * plaintext: the vaulting only happens later, in the handler.
 */
final class PatchServiceCommandLoggingTest extends TestCase
{
    public function testAPasswordMacroNeverReachesTheLogs(): void
    {
        SensitivityScanner::reset();
        SensitiveKeywordDenylist::reset();

        $command = new PatchServiceCommand(
            id: new ServiceId(1),
            actor: TestActingUsers::unrestricted(1),
            macros: ServiceMacroChanges::upsert([new ServiceMacroChange(new ServiceMacroName('token'), 'top-secret', isPassword: true)]),
        );

        $payload = new LogPayloadNormalizer(new CamelCaseToSnakeCaseNameConverter())->normalize($command);
        $sanitised = new PayloadSanitizer()->sanitize($payload, PatchServiceCommand::class);

        self::assertIsArray($sanitised);
        self::assertSame('***', $sanitised['macros']);
        self::assertStringNotContainsString('top-secret', json_encode($sanitised, JSON_THROW_ON_ERROR));
        self::assertSame(['user_id' => '1', 'unrestricted_resource_access' => true], $sanitised['actor'], 'The acting user must stay readable in the logs.');
    }
}
