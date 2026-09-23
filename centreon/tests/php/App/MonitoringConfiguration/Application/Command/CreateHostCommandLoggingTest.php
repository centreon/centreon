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

use App\MonitoringConfiguration\Application\Command\CreateHostCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\Logging\Attribute\SensitivityScanner;
use App\Shared\Infrastructure\Logging\LogPayloadNormalizer;
use App\Shared\Infrastructure\Logging\PayloadSanitizer;
use App\Shared\Infrastructure\Logging\SensitiveKeywordDenylist;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * `LoggingMiddleware` logs the command payload on every dispatch, and the plaintext community
 * travels on the command: the vaulting only happens later, in the handler. Nothing in the keyword
 * denylist matches "snmp community", so only `#[Sensitive]` keeps it out of the logs.
 */
final class CreateHostCommandLoggingTest extends TestCase
{
    public function testTheSnmpCommunityNeverReachesTheLogs(): void
    {
        SensitivityScanner::reset();
        SensitiveKeywordDenylist::reset();

        $command = new CreateHostCommand(
            name: new HostName('server-01'),
            address: new HostAddress('127.0.0.1'),
            pollerId: new PollerId(1),
            hostGroupIds: new Collection([], HostGroupId::class),
            creatorId: 1,
            snmpCommunity: 'top-secret',
        );

        $payload = new LogPayloadNormalizer(new CamelCaseToSnakeCaseNameConverter())->normalize($command);
        $sanitised = new PayloadSanitizer()->sanitize($payload, CreateHostCommand::class);

        self::assertIsArray($sanitised);
        self::assertSame('***', $sanitised['snmp_community']);
        // The rest of the payload still goes through, so the masking is targeted.
        self::assertArrayHasKey('name', $sanitised);
    }
}
