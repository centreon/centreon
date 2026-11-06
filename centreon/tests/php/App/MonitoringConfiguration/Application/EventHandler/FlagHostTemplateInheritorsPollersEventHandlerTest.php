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

namespace Tests\App\MonitoringConfiguration\Application\EventHandler;

use App\MonitoringConfiguration\Application\EventHandler\FlagHostTemplateInheritorsPollersEventHandler;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Event\HostTemplateUpdated;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakePollerRepository;

final class FlagHostTemplateInheritorsPollersEventHandlerTest extends KernelTestCase
{
    private FakeHostTemplateRepository $hostTemplateRepository;

    private FakePollerRepository $pollerRepository;

    private FlagHostTemplateInheritorsPollersEventHandler $handler;

    protected function setUp(): void
    {
        $container = self::getContainer();
        $this->hostTemplateRepository = new FakeHostTemplateRepository();
        $this->pollerRepository = new FakePollerRepository();
        $container->set(HostTemplateRepository::class, $this->hostTemplateRepository);
        $container->set(PollerRepository::class, $this->pollerRepository);

        /** @var FlagHostTemplateInheritorsPollersEventHandler $handler */
        $handler = $container->get(FlagHostTemplateInheritorsPollersEventHandler::class);
        $this->handler = $handler;
    }

    #[DataProvider('provideLoggable')]
    public function testItFlagsThePollersOfTheInheritingHostsWhetherTheChangeIsLoggedOrNot(bool $loggable): void
    {
        $this->hostTemplateRepository->inheritingHostPollerIds[7] = [1, 3];

        ($this->handler)(new HostTemplateUpdated($this->template(7), 5, loggable: $loggable));

        self::assertSame([1, 3], $this->pollerRepository->flaggedPollerIds);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function provideLoggable(): iterable
    {
        yield 'logged' => [true];

        yield 'not logged' => [false];
    }

    public function testATemplateNoHostInheritsFlagsNothing(): void
    {
        $this->hostTemplateRepository->inheritingHostPollerIds[8] = [1];

        ($this->handler)(new HostTemplateUpdated($this->template(7), 5));

        self::assertSame([], $this->pollerRepository->flaggedPollerIds);
    }

    private function template(int $id): HostTemplate
    {
        return new HostTemplate(new HostTemplateId($id), new HostTemplateName('tpl'));
    }
}
