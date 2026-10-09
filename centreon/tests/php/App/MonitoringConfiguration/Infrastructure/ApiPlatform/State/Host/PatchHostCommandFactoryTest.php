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
use App\MonitoringConfiguration\Application\Command\ListChange;
use App\MonitoringConfiguration\Application\Command\ListChangeModeEnum;
use App\MonitoringConfiguration\Application\Command\NotificationsChanges;
use App\MonitoringConfiguration\Application\Command\SchedulingOptionsChanges;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
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

    public function testTheKeyOfAListTellsHowItChanges(): void
    {
        $replaced = $this->create(new PatchHostInput(hostGroupIds: [1, 2]), '{"host_group_ids": [1, 2]}');
        $added = $this->create(new PatchHostInput(templateIdsToAdd: [3]), '{"template_ids_to_add": [3]}');
        $removed = $this->create(new PatchHostInput(parentHostIdsToRemove: [4]), '{"parent_host_ids_to_remove": [4]}');
        $cleared = $this->create(new PatchHostInput(categoryIds: []), '{"category_ids": []}');
        $untouched = $this->create(new PatchHostInput(), '{}');

        self::assertInstanceOf(ListChange::class, $replaced->hostGroupIds);
        self::assertSame(ListChangeModeEnum::Replace, $replaced->hostGroupIds->mode);
        self::assertSame([1, 2], array_map(static fn (HostGroupId $id): int => $id->value, $replaced->hostGroupIds->values));
        self::assertInstanceOf(ListChange::class, $added->templateIds);
        self::assertSame(ListChangeModeEnum::Add, $added->templateIds->mode);
        self::assertInstanceOf(ListChange::class, $removed->parentHostIds);
        self::assertSame(ListChangeModeEnum::Remove, $removed->parentHostIds->mode);
        self::assertInstanceOf(ListChange::class, $cleared->categoryIds);
        self::assertSame([], $cleared->categoryIds->values);
        self::assertInstanceOf(NoValue::class, $untouched->hostGroupIds);
        self::assertInstanceOf(NoValue::class, $untouched->childHostIds);
        self::assertInstanceOf(NoValue::class, $untouched->contactIds);
    }

    public function testTheContactsAreReadInsideTheNotifications(): void
    {
        $command = $this->create(
            new PatchHostInput(notifications: new PatchHostNotificationsInput(contactsToAdd: [5], contactGroups: [6])),
            '{"notifications": {"contacts_to_add": [5], "contact_groups": [6]}}',
        );

        self::assertInstanceOf(ListChange::class, $command->contactIds);
        self::assertSame(ListChangeModeEnum::Add, $command->contactIds->mode);
        self::assertInstanceOf(ListChange::class, $command->contactGroupIds);
        self::assertSame(ListChangeModeEnum::Replace, $command->contactGroupIds->mode);
    }

    public function testTheServicesOfTheTemplatesAreOnlyCreatedWhenAsked(): void
    {
        self::assertTrue($this->create(new PatchHostInput(createServicesLinkedToTemplates: true), '{"create_services_linked_to_templates": true}')->deployServicesFromTemplates);
        self::assertFalse($this->create(new PatchHostInput(), '{}')->deployServicesFromTemplates);
    }

    public function testTheOptionsCanBeAddedToAndRemovedFrom(): void
    {
        $added = $this->create(
            new PatchHostInput(notifications: new PatchHostNotificationsInput(optionsToAdd: ['flapping'])),
            '{"notifications": {"options_to_add": ["flapping"]}}',
        );

        self::assertInstanceOf(NotificationsChanges::class, $added->notifications);
        self::assertInstanceOf(ListChange::class, $added->notifications->options);
        self::assertSame(ListChangeModeEnum::Add, $added->notifications->options->mode);
        self::assertSame([NotificationOptionEnum::Flapping], $added->notifications->options->values);
    }

    public function testTheNotificationOptionsAreMappedToTheDomain(): void
    {
        $command = $this->create(
            new PatchHostInput(notifications: new PatchHostNotificationsInput(options: ['down', 'recovery'])),
            '{"notifications": {"options": ["down", "recovery"]}}',
        );

        self::assertInstanceOf(NotificationsChanges::class, $command->notifications);
        self::assertInstanceOf(ListChange::class, $command->notifications->options);
        self::assertSame(ListChangeModeEnum::Replace, $command->notifications->options->mode);
        self::assertSame([NotificationOptionEnum::Down, NotificationOptionEnum::Recovery], $command->notifications->options->values);
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
