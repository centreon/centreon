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

namespace Tests\App\MonitoringConfiguration\Domain\Model\ServiceListing;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Model\ServiceListing\ServiceListItem;
use PHPUnit\Framework\TestCase;

final class ServiceListItemTest extends TestCase
{
    public function testNamesAreKeptRaw(): void
    {
        // Listed names are returned as stored: no trim, no decoding of the legacy #S# / #BS# markers.
        $item = new ServiceListItem(new ServiceId(1), ' Disk#S#root ', true, new HostId(2), ' central ');

        self::assertSame(' Disk#S#root ', $item->name);
        self::assertSame(' central ', $item->hostName);
    }

    public function testATemplateCarriesItsIdAndItsName(): void
    {
        $item = new ServiceListItem(new ServiceId(1), 'Ping', true, new HostId(2), 'central', templateId: 3, templateName: 'generic-service');

        self::assertSame(3, $item->templateId);
        self::assertSame('generic-service', $item->templateName);
    }

    public function testATemplateIdWithoutItsNameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceListItem(new ServiceId(1), 'Ping', true, new HostId(2), 'central', templateId: 3);
    }

    public function testATemplateNameWithoutItsIdIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceListItem(new ServiceId(1), 'Ping', true, new HostId(2), 'central', templateName: 'generic-service');
    }

    public function testATemplateIdIsPositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceListItem(new ServiceId(1), 'Ping', true, new HostId(2), 'central', templateId: 0, templateName: 'generic-service');
    }
}
