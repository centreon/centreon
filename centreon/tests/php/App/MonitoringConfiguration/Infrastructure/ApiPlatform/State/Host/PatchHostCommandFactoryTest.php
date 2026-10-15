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
use App\MonitoringConfiguration\Application\Command\MacroChangeModeEnum;
use App\MonitoringConfiguration\Application\Command\MacroChanges;
use App\MonitoringConfiguration\Application\Command\NotificationsChanges;
use App\MonitoringConfiguration\Application\Command\SchedulingOptionsChanges;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreateHostExtendedInformationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreateHostSchedulingOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\DataProcessingInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\HostMacroInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\PatchHostCheckOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\PatchHostInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\PatchHostNotificationsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostMacroTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\PatchHostCommandFactory;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\NoValue;
use App\Shared\Infrastructure\ApiPlatform\RequestPayload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Tests\App\Security\Infrastructure\Double\TestActingUsers;

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

    public function testTheMacrosAreReplacedWrittenOrRemovedFromTheKeySent(): void
    {
        $replaced = $this->create(
            new PatchHostInput(checkOptions: new PatchHostCheckOptionsInput(macros: [new HostMacroInput(name: 'port', value: '80', id: 10)])),
            '{"check_options": {"macros": [{"id": 10, "name": "port", "value": "80"}]}}',
        );
        self::assertInstanceOf(MacroChanges::class, $replaced->macros);
        self::assertSame(MacroChangeModeEnum::Replace, $replaced->macros->mode);
        self::assertSame(10, $replaced->macros->changes[0]->id?->value);

        $written = $this->create(
            new PatchHostInput(checkOptions: new PatchHostCheckOptionsInput(macrosToUpsert: [new HostMacroInput(name: 'token', value: 'x', isPassword: true)])),
            '{"check_options": {"macros_to_upsert": [{"name": "token", "value": "x", "is_password": true}]}}',
        );
        self::assertInstanceOf(MacroChanges::class, $written->macros);
        self::assertSame(MacroChangeModeEnum::Upsert, $written->macros->mode);
        self::assertTrue($written->macros->changes[0]->isPassword);

        $removed = $this->create(
            new PatchHostInput(checkOptions: new PatchHostCheckOptionsInput(macrosToRemove: [10, 11])),
            '{"check_options": {"macros_to_remove": [10, 11]}}',
        );
        self::assertInstanceOf(MacroChanges::class, $removed->macros);
        self::assertSame(MacroChangeModeEnum::Remove, $removed->macros->mode);
        self::assertSame([10, 11], array_map(static fn (HostMacroId $id): int => $id->value, $removed->macros->removedIds));
    }

    public function testTheMacrosAreLeftAloneWhenNoKeyIsSent(): void
    {
        $command = $this->create(
            new PatchHostInput(checkOptions: new PatchHostCheckOptionsInput(args: ['a'])),
            '{"check_options": {"args": ["a"]}}',
        );

        self::assertInstanceOf(NoValue::class, $command->macros);
    }

    /**
     * @param ?class-string $nestedClass
     */
    #[DataProvider('everyPropertyOfTheInput')]
    public function testEveryPropertyOfTheInputReachesTheCommand(string $property, ?string $nestedClass, ?string $nestedProperty, ?string $sectionKey): void
    {
        $name = $nestedProperty ?? $property;
        $parameter = (new \ReflectionClass($nestedClass ?? PatchHostInput::class))->getConstructor()?->getParameters();
        $parameter = array_values(array_filter($parameter ?? [], static fn (\ReflectionParameter $candidate): bool => $candidate->getName() === $name))[0];
        [$value, $json] = self::sampleOf($parameter);

        if ($nestedClass === null) {
            $input = (new \ReflectionClass(PatchHostInput::class))->newInstanceArgs([$property => $value]);
            $body = [RequestPayload::keyOf($property) => $json];
            $untouched = $this->create(new PatchHostInput(), '{}');
        } else {
            $input = (new \ReflectionClass(PatchHostInput::class))->newInstanceArgs([$property => (new \ReflectionClass($nestedClass))->newInstanceArgs([$name => $value])]);
            $body = [$sectionKey => [RequestPayload::keyOf($name) => $json]];
            // The sub-object is sent in both, so that only the property tested can make the difference.
            $untouched = $this->create((new \ReflectionClass(PatchHostInput::class))->newInstanceArgs([$property => new $nestedClass()]), json_encode([$sectionKey => new \stdClass()], \JSON_THROW_ON_ERROR));
        }

        $command = $this->create($input, json_encode($body, \JSON_THROW_ON_ERROR));

        self::assertNotEquals($untouched, $command, sprintf('"%s" is read from the input but never reaches the command.', $sectionKey === null ? RequestPayload::keyOf($name) : $sectionKey . '.' . RequestPayload::keyOf($name)));
    }

    /**
     * Every property of the input and of its sub-objects, found by reflection: a property added to the input
     * without being read by the factory makes this fail, with no test to write for it.
     *
     * @return iterable<string, array{string, ?class-string, ?string, ?string}>
     */
    public static function everyPropertyOfTheInput(): iterable
    {
        $subObjects = [
            'dataProcessing' => [DataProcessingInput::class, 'data_processing'],
            'extendedInformations' => [CreateHostExtendedInformationsInput::class, 'extended_informations'],
            'schedulingOptions' => [CreateHostSchedulingOptionsInput::class, 'scheduling_options'],
            'checkOptions' => [PatchHostCheckOptionsInput::class, 'check_options'],
            'notifications' => [PatchHostNotificationsInput::class, 'notifications'],
        ];

        foreach ((new \ReflectionClass(PatchHostInput::class))->getConstructor()?->getParameters() ?? [] as $parameter) {
            if (! isset($subObjects[$parameter->getName()])) {
                yield $parameter->getName() => [$parameter->getName(), null, null, null];

                continue;
            }

            [$class, $sectionKey] = $subObjects[$parameter->getName()];
            foreach ((new \ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $nested) {
                yield $sectionKey . '.' . $nested->getName() => [$parameter->getName(), $class, $nested->getName(), $sectionKey];
            }
        }
    }

    /**
     * A value for the parameter that is not its default, as the input holds it and as the body carries it.
     *
     * @return array{mixed, mixed}
     */
    private static function sampleOf(\ReflectionParameter $parameter): array
    {
        $name = $parameter->getName();
        $type = $parameter->getType();
        $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed';

        if ($name === 'macros' || $name === 'macrosToUpsert') {
            return [[new HostMacroInput(name: 'A', value: 'v')], [['name' => 'A', 'value' => 'v']]];
        }

        if (\in_array($name, ['options', 'optionsToAdd', 'optionsToRemove'], true)) {
            return [['down'], ['down']];
        }

        if (\in_array($name, ['args', 'eventHandlerArgs'], true)) {
            return [['a'], ['a']];
        }

        if ($typeName === 'array') {
            return [[1], [1]];
        }

        if (is_a($typeName, \BackedEnum::class, true)) {
            $case = $typeName::cases()[0];

            return [$case, $case->value];
        }

        return match ($typeName) {
            'int' => [1, 1],
            'bool' => [true, true],
            'string' => match ($name) {
                'name' => ['a-name', 'a-name'],
                'address' => ['10.0.0.9', '10.0.0.9'],
                'enabled' => ['true', 'true'],
                'geoCoordinates' => ['10.5,20.5', '10.5,20.5'],
                default => ['x', 'x'],
            },
            default => throw new \LogicException(sprintf('No sample for the type "%s" of "%s".', $typeName, $name)),
        };
    }

    private function create(PatchHostInput $input, string $json): \App\MonitoringConfiguration\Application\Command\PatchHostCommand
    {
        return new PatchHostCommandFactory(new HostMacroTransformer())->create(
            id: new HostId(1),
            actor: TestActingUsers::restricted(7),
            input: $input,
            payload: RequestPayload::fromRequest(Request::create('/', Request::METHOD_PATCH, content: $json, server: ['CONTENT_TYPE' => 'application/json'])),
        );
    }
}
