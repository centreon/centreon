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

use App\MonitoringConfiguration\Application\Command\ListChange;
use App\MonitoringConfiguration\Application\Command\PatchServiceCommand;
use App\MonitoringConfiguration\Application\Command\ServiceDataProcessingChanges;
use App\MonitoringConfiguration\Application\Command\ServiceExtendedInformationsChanges;
use App\MonitoringConfiguration\Application\Command\ServiceNotificationsChanges;
use App\MonitoringConfiguration\Application\Command\ServiceSchedulingOptionsChanges;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceStalkingOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\MonitoringConfiguration\Domain\Exception\ServiceFieldsNotAvailableOnCloudException;
use App\Shared\Domain\Aggregate\TriStateEnum;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The handler on a Cloud platform, the platform flag read from the environment when the container builds the
 * handler.
 */
final class PatchServiceCommandHandlerCloudTest extends PatchServiceCommandHandlerTestCase
{
    /** @var array{mixed, mixed} */
    private array $previousEnv;

    protected function setUp(): void
    {
        $this->previousEnv = [$_ENV['IS_CLOUD_PLATFORM'] ?? null, $_SERVER['IS_CLOUD_PLATFORM'] ?? null];
        $_ENV['IS_CLOUD_PLATFORM'] = $_SERVER['IS_CLOUD_PLATFORM'] = '1';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        [$previousEnv, $previousServer] = $this->previousEnv;
        if ($previousEnv === null) {
            unset($_ENV['IS_CLOUD_PLATFORM']);
        } else {
            $_ENV['IS_CLOUD_PLATFORM'] = $previousEnv;
        }
        if ($previousServer === null) {
            unset($_SERVER['IS_CLOUD_PLATFORM']);
        } else {
            $_SERVER['IS_CLOUD_PLATFORM'] = $previousServer;
        }

        parent::tearDown();
    }

    /**
     * @param \Closure(self): PatchServiceCommand $command
     * @param array<string, list<string>|true> $criteria
     */
    #[DataProvider('onPremiseOnlyProvider')]
    public function testItRefusesTheFieldsACloudPlatformDoesNotHave(\Closure $command, array $criteria): void
    {
        try {
            $this->patch($command($this));
            self::fail('The field must be refused on a Cloud platform.');
        } catch (ServiceFieldsNotAvailableOnCloudException $exception) {
            self::assertSame($criteria, $exception->criteria);
        }

        $this->assertNothingWritten();
    }

    /**
     * @return iterable<string, array{\Closure(self): PatchServiceCommand, array<string, list<string>|true>}>
     */
    public static function onPremiseOnlyProvider(): iterable
    {
        yield 'no service template' => [static fn (self $test): PatchServiceCommand => $test->command(templateId: null), ['serviceTemplateId' => true]];

        yield 'a graph template' => [static fn (self $test): PatchServiceCommand => $test->command(graphTemplateId: 60), ['graphTemplateId' => true]];

        yield 'traps' => [static fn (self $test): PatchServiceCommand => $test->command(trapIds: ListChange::remove([new TrapId(100)])), ['trapIds' => true]];

        yield 'notification settings' => [static fn (self $test): PatchServiceCommand => $test->command(notifications: new ServiceNotificationsChanges(interval: 5)), ['notifications' => true]];

        yield 'contacts' => [static fn (self $test): PatchServiceCommand => $test->command(contactIds: ListChange::add([new NotificationContactId(31)])), ['notifications' => true]];

        yield 'monitoring engine' => [
            static fn (self $test): PatchServiceCommand => $test->command(
                schedulingOptions: new ServiceSchedulingOptionsChanges(activeCheckEnabled: TriStateEnum::False, isVolatile: TriStateEnum::True),
                extendedInformations: new ServiceExtendedInformationsChanges(altIcon: 'icon'),
            ),
            ['monitoringEngine' => ['activeCheckEnabled', 'isVolatile', 'altIcon']],
        ];

        yield 'advanced' => [
            static fn (self $test): PatchServiceCommand => $test->command(
                dataProcessing: new ServiceDataProcessingChanges(
                    flapDetectionEnabled: TriStateEnum::True,
                    obsessOverService: TriStateEnum::False,
                    retainStatusInformation: TriStateEnum::True,
                    retainNonStatusInformation: TriStateEnum::True,
                    acknowledgmentTimeout: 5,
                    lowFlapThreshold: 10,
                    highFlapThreshold: 20,
                    eventHandlerArgs: ['restart'],
                    stalkingOptions: self::stalking(ServiceStalkingOptionEnum::Ok),
                ),
                extendedInformations: new ServiceExtendedInformationsChanges(comment: 'a comment'),
            ),
            ['advanced' => [
                'obsessOverService', 'flapDetectionEnabled', 'retainStatusInformation', 'retainNonStatusInformation',
                'acknowledgmentTimeout', 'lowFlapThreshold', 'highFlapThreshold', 'stalkingOptions', 'eventHandlerArgs', 'comment',
            ]],
        ];
    }

    /**
     * Clearing a field the platform does not have sets it back to its default: accepted.
     */
    public function testItAcceptsClearingThem(): void
    {
        $this->seedEveryReference();

        $this->patch($this->command(
            name: 'pong',
            templateId: 30,
            graphTemplateId: null,
            trapIds: ListChange::replace([]),
            schedulingOptions: new ServiceSchedulingOptionsChanges(isVolatile: TriStateEnum::UseDefault),
            extendedInformations: new ServiceExtendedInformationsChanges(altIcon: null, comment: null),
            dataProcessing: new ServiceDataProcessingChanges(acknowledgmentTimeout: null, eventHandlerArgs: [], stalkingOptions: ListChange::replace([])),
        ));

        self::assertSame('pong', $this->written()->name->value);
    }

    /**
     * @return ListChange<ServiceStalkingOptionEnum>
     */
    private static function stalking(ServiceStalkingOptionEnum ...$options): ListChange
    {
        return ListChange::add(array_values($options));
    }
}
