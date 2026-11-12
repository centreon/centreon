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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\GraphTemplate\GraphTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceGroup\ServiceGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\Shared\Domain\Aggregate\AclScopedInterface;
use App\Shared\Domain\Aggregate\PollerScopedInterface;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\Shared\Double\FakeVault;

final class ServiceTest extends TestCase
{
    public function testItHoldsItsFields(): void
    {
        $service = $this->service(id: 42, hostId: 1);

        self::assertSame(42, $service->id()->value);
        self::assertSame('web-check', $service->name->value);
        self::assertSame(1, $service->hostId->value);
        self::assertSame([], $service->checkOptions->macros);
    }

    public function testItHasNoOwnValueByDefaultAndIsEnabled(): void
    {
        $service = $this->service();

        self::assertTrue($service->activated);
        self::assertNull($service->templateId);
        self::assertEquals(new ServiceCheckOptions(null), $service->checkOptions);
        self::assertEquals(new ServiceSchedulingOptions(), $service->schedulingOptions);
        self::assertNull($service->notifications);
        self::assertEquals(new ServiceDataProcessing(), $service->dataProcessing);
        self::assertNull($service->extendedInformations);
        self::assertNull($service->graphTemplateId);
        self::assertNull($service->severityId);
        self::assertSame([], $service->serviceGroupIds->toArray());
        self::assertSame([], $service->categoryIds->toArray());
        self::assertSame([], $service->trapIds->toArray());
    }

    public function testItHoldsItsWholeConfiguration(): void
    {
        $checkOptions = new ServiceCheckOptions(new CommandId(2), ['80']);
        $schedulingOptions = new ServiceSchedulingOptions(normalCheckInterval: 5, retryCheckInterval: 1);
        $notifications = new ServiceNotifications(
            TriStateEnum::True,
            new Collection([], NotificationContactId::class),
            new Collection([], ContactGroupId::class),
        );
        $dataProcessing = new ServiceDataProcessing(obsessOverService: TriStateEnum::False);
        $extendedInformations = new ServiceExtendedInformations(iconId: new MediaId(8));

        $service = new Service(
            id: new ServiceId(42),
            name: new ServiceName('web-check'),
            hostId: new HostId(1),
            hostActivated: true,
            hostPollerId: new PollerId(3),
            activated: false,
            templateId: new ServiceTemplateId(7),
            checkOptions: $checkOptions,
            schedulingOptions: $schedulingOptions,
            notifications: $notifications,
            dataProcessing: $dataProcessing,
            extendedInformations: $extendedInformations,
            graphTemplateId: new GraphTemplateId(4),
            severityId: new ServiceSeverityId(6),
            serviceGroupIds: new Collection([new ServiceGroupId(10)], ServiceGroupId::class),
            categoryIds: new Collection([new ServiceCategoryId(11)], ServiceCategoryId::class),
            trapIds: new Collection([new TrapId(12)], TrapId::class),
        );

        self::assertFalse($service->activated);
        self::assertSame(7, $service->templateId?->value);
        self::assertSame($checkOptions, $service->checkOptions);
        self::assertSame($schedulingOptions, $service->schedulingOptions);
        self::assertSame($notifications, $service->notifications);
        self::assertSame($dataProcessing, $service->dataProcessing);
        self::assertSame($extendedInformations, $service->extendedInformations);
        self::assertSame(4, $service->graphTemplateId?->value);
        self::assertSame(6, $service->severityId?->value);
        self::assertEquals([new ServiceGroupId(10)], $service->serviceGroupIds->toArray());
        self::assertEquals([new ServiceCategoryId(11)], $service->categoryIds->toArray());
        self::assertEquals([new TrapId(12)], $service->trapIds->toArray());
    }

    public function testDisableThenEnable(): void
    {
        $service = $this->service();

        $service->disable();
        self::assertFalse($service->activated);

        $service->enable();
        self::assertTrue($service->activated);
    }

    public function testItIsAclAndPollerScoped(): void
    {
        // The markers route every Service event through the generic ACL and poller handlers.
        $interfaces = class_implements(Service::class);

        self::assertIsArray($interfaces);
        self::assertArrayHasKey(AclScopedInterface::class, $interfaces);
        self::assertArrayHasKey(PollerScopedInterface::class, $interfaces);
    }

    public function testThePollerToFlagIsTheOneOfItsEnabledHost(): void
    {
        $service = $this->service(hostActivated: true, hostPollerId: 3);

        self::assertSame(3, $service->pollerToFlag()?->value);
    }

    public function testNoPollerIsFlaggedForAServiceOfADisabledHost(): void
    {
        $service = $this->service(hostActivated: false, hostPollerId: 3);

        self::assertNull($service->pollerToFlag());
    }

    public function testNoPollerIsFlaggedForAServiceWhoseHostHasNoPoller(): void
    {
        $service = $this->service(hostActivated: true, hostPollerId: null);

        self::assertNull($service->pollerToFlag());
    }

    public function testGetVaultUuidReturnsNullWhenNothingIsVaulted(): void
    {
        $service = $this->service();

        self::assertNull($service->getVaultUuid(new FakeVault()));
    }

    public function testGetVaultUuidReturnsTheFirstVaultedPasswordMacro(): void
    {
        $vault = new FakeVault();
        $vault->extractedUuids['secret::vault::monitoring/services/uuid-1::_SERVICETOKEN'] = 'uuid-1';
        $macro = new ServiceMacro(
            new ServiceMacroName('token'),
            'secret::vault::monitoring/services/uuid-1::_SERVICETOKEN',
            isPassword: true,
        );
        $service = $this->service(macros: [$macro]);

        self::assertSame('uuid-1', $service->getVaultUuid($vault));
    }

    public function testGetVaultUuidIgnoresANonPasswordMacroEvenIfItLooksLikeAVaultPath(): void
    {
        $vault = new FakeVault();
        $vault->extractedUuids['secret::vault::monitoring/services/uuid-2::_SERVICENOTASECRET'] = 'uuid-2';
        $macro = new ServiceMacro(
            new ServiceMacroName('notasecret'),
            'secret::vault::monitoring/services/uuid-2::_SERVICENOTASECRET',
            isPassword: false,
        );
        $service = $this->service(macros: [$macro]);

        self::assertNull($service->getVaultUuid($vault));
    }

    /**
     * @param list<ServiceMacro> $macros
     */
    private function service(
        ?int $id = null,
        int $hostId = 1,
        array $macros = [],
        bool $hostActivated = true,
        ?int $hostPollerId = 1,
    ): Service {
        return new Service(
            id: $id !== null ? new ServiceId($id) : null,
            name: new ServiceName('web-check'),
            hostId: new HostId($hostId),
            hostActivated: $hostActivated,
            hostPollerId: $hostPollerId !== null ? new PollerId($hostPollerId) : null,
            checkOptions: new ServiceCheckOptions(null, [], $macros),
        );
    }
}
