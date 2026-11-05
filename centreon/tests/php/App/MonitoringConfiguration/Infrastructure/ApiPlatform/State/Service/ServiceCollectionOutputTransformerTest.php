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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckIntervals;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceCollectionOutputTransformer;
use PHPUnit\Framework\TestCase;

final class ServiceCollectionOutputTransformerTest extends TestCase
{
    public function testTheServicesOwnScalarsAreMapped(): void
    {
        $service = new Service(
            id: new ServiceId(1),
            name: new ServiceName('ping'),
            hostId: new HostId(2),
            activated: false,
            templateId: new ServiceTemplateId(3),
            iconId: new MediaId(4),
            checkIntervals: new ServiceCheckIntervals(normal: 5, retry: 1),
        );

        $output = (new ServiceCollectionOutputTransformer())->transform($service);

        self::assertSame(1, $output->id);
        self::assertSame('ping', $output->name);
        self::assertFalse($output->activated);
    }

    public function testTheValuesTheProviderResolvesAreLeftUnset(): void
    {
        // The host, the icon, the intervals and the template are resolved per page by the provider, from lookups.
        $output = (new ServiceCollectionOutputTransformer())->transform(
            new Service(new ServiceId(1), new ServiceName('ping'), new HostId(2), checkIntervals: new ServiceCheckIntervals(normal: 5)),
        );

        self::assertNull($output->icon);
        self::assertNull($output->normalCheckInterval);
        self::assertNull($output->retryCheckInterval);
        self::assertNull($output->template);
    }
}
