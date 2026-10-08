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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use App\MonitoringConfiguration\Application\Command\CheckOptionsChanges;
use App\MonitoringConfiguration\Application\Command\DataProcessingChanges;
use App\MonitoringConfiguration\Application\Command\NotificationsChanges;
use App\MonitoringConfiguration\Application\Command\SchedulingOptionsChanges;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreateHostSchedulingOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\DataProcessingInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\PatchHostCheckOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\PatchHostInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\PatchHostNotificationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\PatchHostCommandFactory;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\NoValue;
use App\Shared\Infrastructure\ApiPlatform\RequestPayload;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PatchHostCommandFactoryTest extends TestCase
{
    public function testAKeyLeftOutIsLeftUntouched(): void
    {
        $command = $this->create(new PatchHostInput(alias: 'front'), '{"alias": "front"}');

        self::assertInstanceOf(HostAlias::class, $command->alias);
        self::assertSame('front', $command->alias->value);
        self::assertInstanceOf(NoValue::class, $command->name);
        self::assertInstanceOf(NoValue::class, $command->pollerId);
        self::assertInstanceOf(NoValue::class, $command->snmpCommunity);
        self::assertInstanceOf(NoValue::class, $command->notifications);
    }

    public function testAKeySentAsNullClearsTheValue(): void
    {
        $command = $this->create(new PatchHostInput(), '{"alias": null, "timezone_id": null, "snmp_community": null}');

        self::assertNull($command->alias);
        self::assertNull($command->timezoneId);
        self::assertNull($command->snmpCommunity);
    }

    public function testABlankTextClearsTheValueToo(): void
    {
        $command = $this->create(new PatchHostInput(alias: '   '), '{"alias": "   "}');

        self::assertNull($command->alias);
    }

    public function testASubObjectKeyLeftOutStaysUntouchedInsideASentSubObject(): void
    {
        $command = $this->create(
            new PatchHostInput(schedulingOptions: new CreateHostSchedulingOptionsInput(maxCheckAttempts: 5)),
            '{"scheduling_options": {"max_check_attempts": 5}}',
        );

        self::assertInstanceOf(SchedulingOptionsChanges::class, $command->schedulingOptions);
        self::assertSame(5, $command->schedulingOptions->maxCheckAttempts);
        self::assertInstanceOf(NoValue::class, $command->schedulingOptions->normalCheckInterval);
        self::assertInstanceOf(NoValue::class, $command->schedulingOptions->activeCheckEnabled);
    }

    public function testATriStateSentAsNullMeansUseDefault(): void
    {
        $command = $this->create(
            new PatchHostInput(dataProcessing: new DataProcessingInput()),
            '{"data_processing": {"check_freshness": null, "freshness_threshold": null}}',
        );

        self::assertInstanceOf(DataProcessingChanges::class, $command->dataProcessing);
        self::assertSame(TriStateEnum::UseDefault, $command->dataProcessing->checkFreshness);
        self::assertNull($command->dataProcessing->freshnessThreshold);
        self::assertInstanceOf(NoValue::class, $command->dataProcessing->eventHandlerArgs);
    }

    public function testAnEmptyListClearsTheArguments(): void
    {
        $command = $this->create(
            new PatchHostInput(checkOptions: new PatchHostCheckOptionsInput(commandId: null, args: [])),
            '{"check_options": {"command_id": null, "args": []}}',
        );

        self::assertInstanceOf(CheckOptionsChanges::class, $command->checkOptions);
        self::assertNull($command->checkOptions->checkCommandId);
        self::assertSame([], $command->checkOptions->args);
    }

    public function testTheNotificationOptionsAreMappedToTheDomain(): void
    {
        $command = $this->create(
            new PatchHostInput(notifications: new PatchHostNotificationsInput(options: ['down', 'recovery'])),
            '{"notifications": {"options": ["down", "recovery"]}}',
        );

        self::assertInstanceOf(NotificationsChanges::class, $command->notifications);
        self::assertSame([NotificationOptionEnum::Down, NotificationOptionEnum::Recovery], $command->notifications->options);
        self::assertInstanceOf(NoValue::class, $command->notifications->interval);
        self::assertInstanceOf(NoValue::class, $command->notifications->contactAdditiveInheritance);
    }

    private function create(PatchHostInput $input, string $json): \App\MonitoringConfiguration\Application\Command\PatchHostCommand
    {
        return new PatchHostCommandFactory()->create(
            id: new HostId(1),
            updatedBy: 7,
            viewerId: null,
            input: $input,
            payload: RequestPayload::fromRequest(Request::create('/', Request::METHOD_PATCH, content: $json, server: ['CONTENT_TYPE' => 'application/json'])),
        );
    }
}
