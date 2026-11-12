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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service;

use App\MonitoringConfiguration\Application\Command\ListChange;
use App\MonitoringConfiguration\Application\Command\ListChangeModeEnum;
use App\MonitoringConfiguration\Application\Command\MacroChangeModeEnum;
use App\MonitoringConfiguration\Application\Command\PatchServiceCommand;
use App\MonitoringConfiguration\Application\Command\ServiceCheckOptionsChanges;
use App\MonitoringConfiguration\Application\Command\ServiceDataProcessingChanges;
use App\MonitoringConfiguration\Application\Command\ServiceExtendedInformationsChanges;
use App\MonitoringConfiguration\Application\Command\ServiceMacroChanges;
use App\MonitoringConfiguration\Application\Command\ServiceNotificationsChanges;
use App\MonitoringConfiguration\Application\Command\ServiceSchedulingOptionsChanges;
use App\MonitoringConfiguration\Domain\Aggregate\GeoCoordinates;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\PatchServiceAdvancedInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\PatchServiceCheckOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\PatchServiceInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\PatchServiceNotificationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceMacroInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceMonitoringEngineInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\PatchServiceCommandFactory;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceMacroTransformer;
use App\Security\Domain\Aggregate\ActingUser;
use App\Shared\Domain\Aggregate\AggregateRootId;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\NoValue;
use App\Shared\Infrastructure\ApiPlatform\RequestPayload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;

final class PatchServiceCommandFactoryTest extends TestCase
{
    public function testItCarriesTheServiceAndTheActor(): void
    {
        $actor = TestActingUsers::restricted(7);

        $command = $this->create(new PatchServiceInput(), '{}', $actor);

        self::assertSame(12, $command->id->value);
        self::assertSame($actor, $command->actor);
    }

    public function testAnActivationLeftOutIsLeftUntouched(): void
    {
        $command = $this->create(new PatchServiceInput(), '{}');

        self::assertInstanceOf(NoValue::class, $command->activated);
    }

    #[DataProvider('activations')]
    public function testASentActivationIsCarried(bool $activated, string $json): void
    {
        $command = $this->create(new PatchServiceInput(activated: $activated), $json);

        self::assertSame($activated, $command->activated);
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function activations(): iterable
    {
        yield 'enable' => [true, '{"activated": true}'];

        yield 'disable' => [false, '{"activated": false}'];
    }

    public function testAKeyLeftOutIsLeftUntouched(): void
    {
        $command = $this->create(new PatchServiceInput(name: 'pong'), '{"name": "pong"}');

        self::assertInstanceOf(ServiceName::class, $command->name);
        foreach ([
            $command->hostId, $command->templateId, $command->severityId, $command->graphTemplateId, $command->serviceGroupIds,
            $command->categoryIds, $command->trapIds, $command->checkOptions, $command->macros, $command->schedulingOptions,
            $command->extendedInformations, $command->notifications, $command->contactIds, $command->contactGroupIds,
            $command->dataProcessing,
        ] as $field) {
            self::assertInstanceOf(NoValue::class, $field);
        }
    }

    /**
     * Stored as the input measures it: trimmed, inner runs of spaces collapsed.
     */
    public function testTheNameIsNormalised(): void
    {
        $command = $this->create(new PatchServiceInput(name: '  web   check '), '{"name": "  web   check "}');

        self::assertInstanceOf(ServiceName::class, $command->name);
        self::assertSame('web check', $command->name->value);
    }

    public function testAKeySentAsNullClearsTheValue(): void
    {
        $command = $this->create(
            new PatchServiceInput(
                monitoringEngine: new ServiceMonitoringEngineInput(),
                advanced: new PatchServiceAdvancedInput(),
                checkOptions: new PatchServiceCheckOptionsInput(),
            ),
            '{"service_template_id": null, "severity_id": null, "graph_template_id": null, "check_options": {"command_id": null},'
            . ' "monitoring_engine": {"max_check_attempts": null, "is_volatile": null, "note": null},'
            . ' "advanced": {"event_handler_command_id": null, "check_freshness": null, "comment": null}}',
        );

        self::assertNull($command->templateId);
        self::assertNull($command->severityId);
        self::assertNull($command->graphTemplateId);
        self::assertInstanceOf(ServiceCheckOptionsChanges::class, $command->checkOptions);
        self::assertNull($command->checkOptions->checkCommandId);
        self::assertInstanceOf(NoValue::class, $command->checkOptions->args);
        self::assertInstanceOf(ServiceSchedulingOptionsChanges::class, $command->schedulingOptions);
        self::assertNull($command->schedulingOptions->maxCheckAttempts);
        self::assertSame(TriStateEnum::UseDefault, $command->schedulingOptions->isVolatile);
        self::assertInstanceOf(NoValue::class, $command->schedulingOptions->normalCheckInterval);
        self::assertInstanceOf(ServiceExtendedInformationsChanges::class, $command->extendedInformations);
        self::assertNull($command->extendedInformations->note);
        self::assertNull($command->extendedInformations->comment);
        self::assertInstanceOf(NoValue::class, $command->extendedInformations->noteUrl);
        self::assertInstanceOf(ServiceDataProcessingChanges::class, $command->dataProcessing);
        self::assertNull($command->dataProcessing->eventHandlerCommandId);
        self::assertSame(TriStateEnum::UseDefault, $command->dataProcessing->checkFreshness);
        self::assertInstanceOf(NoValue::class, $command->dataProcessing->eventHandlerArgs);
    }

    /**
     * The comment, sent in "advanced", joins the notes and URLs of "monitoring_engine".
     */
    public function testEachSectionFeedsItsFields(): void
    {
        $command = $this->create(
            new PatchServiceInput(
                monitoringEngine: new ServiceMonitoringEngineInput(normalCheckInterval: 5, iconId: 50, geoCoordinates: '48.8,2.3'),
                advanced: new PatchServiceAdvancedInput(lowFlapThreshold: 10, eventHandlerArgs: ['restart'], comment: 'a comment'),
                notifications: new PatchServiceNotificationsInput(enabled: 'false', useOnlyContactsFromHost: true, firstDelay: 0),
            ),
            '{"monitoring_engine": {"normal_check_interval": 5, "icon_id": 50, "geo_coordinates": "48.8,2.3"},'
            . ' "advanced": {"low_flap_threshold": 10, "event_handler_args": ["restart"], "comment": "a comment"},'
            . ' "notifications": {"enabled": "false", "use_only_contacts_from_host": true, "first_delay": 0}}',
        );

        self::assertInstanceOf(ServiceSchedulingOptionsChanges::class, $command->schedulingOptions);
        self::assertSame(5, $command->schedulingOptions->normalCheckInterval);
        self::assertInstanceOf(ServiceExtendedInformationsChanges::class, $command->extendedInformations);
        self::assertSame(50, $command->extendedInformations->iconId instanceof MediaId ? $command->extendedInformations->iconId->value : null);
        self::assertSame('a comment', $command->extendedInformations->comment);
        self::assertInstanceOf(GeoCoordinates::class, $command->extendedInformations->geoCoordinates);
        self::assertInstanceOf(ServiceDataProcessingChanges::class, $command->dataProcessing);
        self::assertSame(10, $command->dataProcessing->lowFlapThreshold);
        self::assertSame(['restart'], $command->dataProcessing->eventHandlerArgs);
        self::assertInstanceOf(ServiceNotificationsChanges::class, $command->notifications);
        self::assertSame(TriStateEnum::False, $command->notifications->enabled);
        self::assertTrue($command->notifications->useOnlyContactsFromHost);
        self::assertSame(0, $command->notifications->firstDelay);
        self::assertInstanceOf(NoValue::class, $command->notifications->interval);
        self::assertInstanceOf(NoValue::class, $command->contactIds);
    }

    /**
     * @param \Closure(PatchServiceCommand): mixed $read
     * @param list<int|string> $values
     */
    #[DataProvider('listKeyProvider')]
    public function testTheListKeySentSelectsItsMode(PatchServiceInput $input, string $json, \Closure $read, ListChangeModeEnum $mode, array $values): void
    {
        $change = $read($this->create($input, $json));

        self::assertInstanceOf(ListChange::class, $change);
        self::assertSame($mode, $change->mode);
        self::assertSame($values, array_map(
            static fn (mixed $value): int|string => match (true) {
                $value instanceof ServiceStalkingOptionEnum, $value instanceof ServiceNotificationOptionEnum => $value->name,
                $value instanceof AggregateRootId => $value->value,
                default => throw new \LogicException('Unexpected list value.'),
            },
            $change->values,
        ));
    }

    /**
     * @return iterable<string, array{PatchServiceInput, string, \Closure(PatchServiceCommand): mixed, ListChangeModeEnum, list<int|string>}>
     */
    public static function listKeyProvider(): iterable
    {
        yield 'service groups replaced' => [
            new PatchServiceInput(serviceGroupIds: [80]),
            '{"service_group_ids": [80]}',
            static fn (PatchServiceCommand $command): mixed => $command->serviceGroupIds,
            ListChangeModeEnum::Replace,
            [80],
        ];

        yield 'categories added to' => [
            new PatchServiceInput(categoryIdsToAdd: [90]),
            '{"category_ids_to_add": [90]}',
            static fn (PatchServiceCommand $command): mixed => $command->categoryIds,
            ListChangeModeEnum::Add,
            [90],
        ];

        yield 'traps removed from' => [
            new PatchServiceInput(trapIdsToRemove: [100]),
            '{"trap_ids_to_remove": [100]}',
            static fn (PatchServiceCommand $command): mixed => $command->trapIds,
            ListChangeModeEnum::Remove,
            [100],
        ];

        yield 'contacts added to' => [
            new PatchServiceInput(notifications: new PatchServiceNotificationsInput(contactsToAdd: [31])),
            '{"notifications": {"contacts_to_add": [31]}}',
            static fn (PatchServiceCommand $command): mixed => $command->contactIds,
            ListChangeModeEnum::Add,
            [31],
        ];

        yield 'contact groups removed from' => [
            new PatchServiceInput(notifications: new PatchServiceNotificationsInput(contactGroupsToRemove: [41])),
            '{"notifications": {"contact_groups_to_remove": [41]}}',
            static fn (PatchServiceCommand $command): mixed => $command->contactGroupIds,
            ListChangeModeEnum::Remove,
            [41],
        ];

        yield 'notification options replaced' => [
            new PatchServiceInput(notifications: new PatchServiceNotificationsInput(options: ['warning', 'downtime_scheduled'])),
            '{"notifications": {"options": ["warning", "downtime_scheduled"]}}',
            static fn (PatchServiceCommand $command): mixed => $command->notifications instanceof ServiceNotificationsChanges ? $command->notifications->options : null,
            ListChangeModeEnum::Replace,
            ['Warning', 'DowntimeScheduled'],
        ];

        yield 'stalking options added to' => [
            new PatchServiceInput(advanced: new PatchServiceAdvancedInput(stalkingOptionsToAdd: ['ok'])),
            '{"advanced": {"stalking_options_to_add": ["ok"]}}',
            static fn (PatchServiceCommand $command): mixed => $command->dataProcessing instanceof ServiceDataProcessingChanges ? $command->dataProcessing->stalkingOptions : null,
            ListChangeModeEnum::Add,
            ['Ok'],
        ];
    }

    /**
     * @param list<int> $removedIds
     */
    #[DataProvider('macroKeyProvider')]
    public function testTheMacroKeySentSelectsItsMode(PatchServiceCheckOptionsInput $checkOptions, string $json, MacroChangeModeEnum $mode, int $changeCount, array $removedIds): void
    {
        $command = $this->create(new PatchServiceInput(checkOptions: $checkOptions), $json);

        self::assertInstanceOf(ServiceMacroChanges::class, $command->macros);
        self::assertSame($mode, $command->macros->mode);
        self::assertCount($changeCount, $command->macros->changes);
        self::assertSame($removedIds, array_map(static fn (ServiceMacroId $id): int => $id->value, $command->macros->removedIds));
    }

    /**
     * @return iterable<string, array{PatchServiceCheckOptionsInput, string, MacroChangeModeEnum, int, list<int>}>
     */
    public static function macroKeyProvider(): iterable
    {
        $macro = new ServiceMacroInput(name: 'port', value: '80');

        yield 'replaced' => [new PatchServiceCheckOptionsInput(macros: [$macro]), '{"check_options": {"macros": [{}]}}', MacroChangeModeEnum::Replace, 1, []];

        yield 'written' => [new PatchServiceCheckOptionsInput(macrosToUpsert: [$macro]), '{"check_options": {"macros_to_upsert": [{}]}}', MacroChangeModeEnum::Upsert, 1, []];

        yield 'removed' => [new PatchServiceCheckOptionsInput(macrosToRemove: [3]), '{"check_options": {"macros_to_remove": [3]}}', MacroChangeModeEnum::Remove, 0, [3]];
    }

    public function testCheckOptionsWithoutMacroKeyLeaveTheMacrosUntouched(): void
    {
        $command = $this->create(new PatchServiceInput(checkOptions: new PatchServiceCheckOptionsInput(args: ['80'])), '{"check_options": {"args": ["80"]}}');

        self::assertInstanceOf(NoValue::class, $command->macros);
        self::assertInstanceOf(ServiceCheckOptionsChanges::class, $command->checkOptions);
        self::assertSame(['80'], $command->checkOptions->args);
        self::assertInstanceOf(NoValue::class, $command->checkOptions->checkCommandId);
    }

    private function create(PatchServiceInput $input, string $json, ?ActingUser $actor = null): PatchServiceCommand
    {
        return (new PatchServiceCommandFactory(new ServiceMacroTransformer()))->create(
            id: new ServiceId(12),
            actor: $actor ?? TestActingUsers::unrestricted(),
            input: $input,
            payload: RequestPayload::fromRequest(Request::create('/', Request::METHOD_PATCH, content: $json, server: ['CONTENT_TYPE' => 'application/json'])),
        );
    }
}
