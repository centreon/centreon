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

use App\MonitoringConfiguration\Domain\Aggregate\Host\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaDirectory;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\MediaRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostCollectionOutputListTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostCollectionOutputTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostIconOutputTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class HostCollectionOutputListTransformerTest extends TestCase
{
    public function testItResolvesEachRelationOnceForTheWholeList(): void
    {
        $pollerRepository = $this->createMock(PollerRepository::class);
        $pollerRepository->expects(self::once())
            ->method('findNamesByIds')
            ->willReturnCallback(static function (Collection $ids): Collection {
                self::assertCount(1, $ids);

                return new Collection([1 => new PollerName('Central')], PollerName::class);
            });

        $hostTemplateRepository = $this->createMock(HostTemplateRepository::class);
        $hostTemplateRepository->expects(self::once())
            ->method('findNamesByIds')
            ->willReturnCallback(static function (Collection $ids): Collection {
                self::assertCount(2, $ids);

                return new Collection([
                    10 => new HostTemplateName('generic-host'),
                    11 => new HostTemplateName('linux-host'),
                ], HostTemplateName::class);
            });

        $mediaRepository = $this->createMock(MediaRepository::class);
        $mediaRepository->expects(self::once())
            ->method('findByIds')
            ->willReturnCallback(static function (Collection $ids): Collection {
                self::assertCount(1, $ids);

                return new Collection([
                    7 => new Media(new MediaId(7), new MediaName('server.png'), new MediaDirectory('hosts')),
                ], Media::class);
            });

        $outputs = (new HostCollectionOutputListTransformer(
            $pollerRepository,
            $hostTemplateRepository,
            $mediaRepository,
            $this->itemTransformer(),
        ))->transform([
            $this->host(id: 1, templateIds: [10], iconId: 7),
            $this->host(id: 2, templateIds: [10, 11], iconId: 7),
            $this->host(id: 3, templateIds: []),
        ]);

        self::assertCount(3, $outputs);
        self::assertSame('Central', $outputs[0]->poller->name);
        self::assertSame(['generic-host', 'linux-host'], array_map(static fn ($template): string => $template->name, $outputs[1]->templates));
        self::assertSame('/img/media/hosts/server.png', $outputs[1]->icon?->url);
        self::assertNull($outputs[2]->icon);
    }

    public function testTheItemTransformerLeavesOutNamesMissingFromTheLookups(): void
    {
        $output = $this->itemTransformer()->transform(
            $this->host(id: 1, templateIds: [10], iconId: 7),
            ['pollerNames' => [], 'templateNames' => [], 'icons' => []],
        );

        self::assertSame(1, $output->poller->id);
        self::assertSame('', $output->poller->name);
        self::assertSame([], $output->templates);
        self::assertNull($output->icon);
    }

    public function testTheItemTransformerRequiresTheLookups(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->itemTransformer()->transform($this->host(id: 1, templateIds: []));
    }

    private function itemTransformer(): HostCollectionOutputTransformer
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/'));

        return new HostCollectionOutputTransformer(
            new HostIconOutputTransformer(new MediaUrlGenerator('/img/media/', $requestStack)),
        );
    }

    /**
     * @param list<int> $templateIds
     */
    private function host(int $id, array $templateIds, ?int $iconId = null): Host
    {
        return new Host(
            id: new HostId($id),
            name: new HostName('host-' . $id),
            alias: null,
            address: new HostAddress('127.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection(array_map(static fn (int $templateId): HostTemplateId => new HostTemplateId($templateId), $templateIds), HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            extendedInformations: $iconId !== null ? new ExtendedInformations(iconId: new MediaId($iconId)) : null,
        );
    }
}
