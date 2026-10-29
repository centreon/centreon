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
use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaDirectory;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaName;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceId;
use App\MonitoringConfiguration\Domain\Model\ServiceListing\ServiceListItem;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceCollectionOutputTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceListItemView;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class ServiceCollectionOutputTransformerTest extends TestCase
{
    private ServiceCollectionOutputTransformer $transformer;

    protected function setUp(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/'));

        $this->transformer = new ServiceCollectionOutputTransformer(new MediaUrlGenerator('/img/media/', $requestStack));
    }

    public function testEveryFieldIsMapped(): void
    {
        $item = new ServiceListItem(
            serviceId: new ServiceId(1),
            name: ' Disk#S#root ',
            activated: false,
            hostId: new HostId(2),
            hostName: ' central ',
            hostIconId: new MediaId(3),
            iconId: new MediaId(4),
            normalCheckInterval: 5,
            retryCheckInterval: 1,
            templateId: 6,
            templateName: 'generic-service',
        );

        $output = $this->transformer->transform(new ServiceListItemView($item, $this->media(3, 'host.png'), $this->media(4, 'disk.png')));

        self::assertSame(1, $output->id);
        self::assertSame(' Disk#S#root ', $output->name);
        self::assertFalse($output->activated);
        self::assertSame(['id' => 2, 'name' => ' central ', 'icon' => ['id' => 3, 'name' => 'host.png', 'url' => '/img/media/dir/host.png']], $output->host);
        self::assertSame(['id' => 4, 'name' => 'disk.png', 'url' => '/img/media/dir/disk.png'], $output->icon);
        self::assertSame(5, $output->normalCheckInterval);
        self::assertSame(1, $output->retryCheckInterval);
        self::assertSame(['id' => 6, 'name' => 'generic-service'], $output->template);
    }

    public function testWhatIsUnresolvedIsLeftOut(): void
    {
        $item = new ServiceListItem(new ServiceId(1), 'ping', true, new HostId(2), 'central');

        $output = $this->transformer->transform(new ServiceListItemView($item, null, null));

        // The nested host carries no icon key at all, and the null properties are skipped when serialized.
        self::assertSame(['id' => 2, 'name' => 'central'], $output->host);
        self::assertNull($output->icon);
        self::assertNull($output->normalCheckInterval);
        self::assertNull($output->retryCheckInterval);
        self::assertNull($output->template);
    }

    private function media(int $id, string $name): Media
    {
        return new Media(new MediaId($id), new MediaName($name), new MediaDirectory('dir'));
    }
}
