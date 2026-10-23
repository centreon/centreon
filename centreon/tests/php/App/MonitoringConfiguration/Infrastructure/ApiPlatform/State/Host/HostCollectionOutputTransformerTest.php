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
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostCollectionOutputTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\HostIconOutputTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media\MediaUrlGenerator;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class HostCollectionOutputTransformerTest extends TestCase
{
    public function testItResolvesTheRelatedNamesFromTheLookups(): void
    {
        $output = $this->transformer()->transform($this->host(templateIds: [10, 11], iconId: 7), [
            'pollerNames' => [1 => new PollerName('Central')],
            'templateNames' => [10 => new HostTemplateName('generic-host'), 11 => new HostTemplateName('linux-host')],
            'icons' => [7 => $this->media(7, 'server.png')],
            'inheritedIconIds' => [],
        ]);

        self::assertSame(1, $output->poller->id);
        self::assertSame('Central', $output->poller->name);
        self::assertSame(['generic-host', 'linux-host'], array_column($output->templates, 'name'));
        self::assertSame('/img/media/hosts/server.png', $output->icon?->url);
    }

    public function testItLeavesOutNamesMissingFromTheLookups(): void
    {
        $output = $this->transformer()->transform(
            $this->host(templateIds: [10], iconId: 7),
            ['pollerNames' => [], 'templateNames' => [], 'icons' => [], 'inheritedIconIds' => []],
        );

        self::assertSame(1, $output->poller->id);
        self::assertSame('', $output->poller->name);
        self::assertSame([], $output->templates);
        self::assertNull($output->icon);
    }

    public function testAHostWithoutItsOwnIconFallsBackToTheOneInheritedFromItsTemplates(): void
    {
        $lookups = [
            'pollerNames' => [],
            'templateNames' => [],
            'icons' => [7 => $this->media(7, 'own.png'), 8 => $this->media(8, 'inherited.png')],
            'inheritedIconIds' => [1 => new MediaId(8)],
        ];

        self::assertSame('inherited.png', $this->transformer()->transform($this->host(templateIds: [10]), $lookups)->icon?->name);
        self::assertSame('own.png', $this->transformer()->transform($this->host(templateIds: [10], iconId: 7), $lookups)->icon?->name);
    }

    public function testItRequiresTheLookups(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->transformer()->transform($this->host(templateIds: []));
    }

    private function transformer(): HostCollectionOutputTransformer
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/'));

        return new HostCollectionOutputTransformer(
            new HostIconOutputTransformer(new MediaUrlGenerator('/img/media/', $requestStack)),
        );
    }

    private function media(int $id, string $name): Media
    {
        return new Media(new MediaId($id), new MediaName($name), new MediaDirectory('hosts'));
    }

    /**
     * @param list<int> $templateIds
     */
    private function host(array $templateIds, ?int $iconId = null): Host
    {
        return new Host(
            id: new HostId(1),
            name: new HostName('host-1'),
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
