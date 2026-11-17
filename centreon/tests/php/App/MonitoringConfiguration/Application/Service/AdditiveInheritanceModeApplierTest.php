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

namespace Tests\App\MonitoringConfiguration\Application\Service;

use App\MonitoringConfiguration\Application\Service\AdditiveInheritanceModeApplier;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceNotifications;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeOptionRepository;

final class AdditiveInheritanceModeApplierTest extends TestCase
{
    private FakeOptionRepository $optionRepository;

    private AdditiveInheritanceModeApplier $applier;

    protected function setUp(): void
    {
        $this->optionRepository = new FakeOptionRepository();
        $this->applier = new AdditiveInheritanceModeApplier($this->optionRepository);
    }

    /**
     * '3' is what a fresh install ships: anything but '1' turns additive inheritance off.
     */
    public function testItForcesTheServiceFlagsOffWhenTheOptionIsDisabled(): void
    {
        $this->optionRepository->options['inheritance_mode'] = '3';

        self::assertEquals($this->notifications(additiveInheritance: false), $this->applier->applyToService($this->notifications(additiveInheritance: true)));
    }

    public function testItForcesTheServiceFlagsOffWhenTheOptionIsMissing(): void
    {
        self::assertEquals($this->notifications(additiveInheritance: false), $this->applier->applyToService($this->notifications(additiveInheritance: true)));
    }

    public function testItKeepsTheServiceFlagsWhenTheOptionIsEnabled(): void
    {
        $this->optionRepository->options['inheritance_mode'] = '1';

        self::assertEquals($this->notifications(additiveInheritance: true), $this->applier->applyToService($this->notifications(additiveInheritance: true)));
    }

    public function testItLeavesAServiceWithoutNotificationsBlockAlone(): void
    {
        self::assertNull($this->applier->applyToService(null));
    }

    private function notifications(bool $additiveInheritance): ServiceNotifications
    {
        return new ServiceNotifications(
            enabled: TriStateEnum::True,
            contactIds: new Collection([new NotificationContactId(7)], NotificationContactId::class),
            contactGroupIds: new Collection([new ContactGroupId(9)], ContactGroupId::class),
            interval: 30,
            contactAdditiveInheritance: $additiveInheritance,
            contactGroupAdditiveInheritance: $additiveInheritance,
            useOnlyContactsFromHost: false,
        );
    }
}
