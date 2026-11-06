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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\ServiceTemplate;

use ApiPlatform\Metadata\Get;
use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\MonitoringConfiguration\Domain\Exception\ServiceTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Service\InheritedServiceMacrosResolver;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceMacroOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Service\ServiceMacroTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\ServiceTemplate\GetServiceTemplateMacrosProvider;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceTemplateRepository;

final class GetServiceTemplateMacrosProviderLogicTest extends TestCase
{
    private FakeServiceTemplateRepository $templates;

    private FakeCommandRepository $commands;

    private GetServiceTemplateMacrosProvider $provider;

    protected function setUp(): void
    {
        $this->templates = new FakeServiceTemplateRepository();
        $this->commands = new FakeCommandRepository();
        $this->provider = new GetServiceTemplateMacrosProvider(
            $this->templates,
            new InheritedServiceMacrosResolver($this->templates, $this->commands),
            new ServiceMacroTransformer(),
        );
    }

    public function testItReturnsTheOwnMacrosThenTheInheritedOnesWithTheirParent(): void
    {
        $this->addCommand(7, '$USER1$/check -a $_SERVICETIMEOUT$ -p $_SERVICEPORT$');
        $this->addTemplate(2, checkCommandId: 7, macros: [$this->macro(20, 'timeout', '10')]);
        $this->addTemplate(1, parentId: 2, macros: [$this->macro(10, 'secret', 's3cr3t', isPassword: true)]);

        $output = $this->provider->provide(new Get(), ['id' => 1]);

        self::assertSame(1, $output->id);
        self::assertSame('tpl-1', $output->name);
        self::assertEquals(
            [
                // own macro: direct, and a password is never echoed
                new ServiceMacroOutput(10, 'SECRET', null, true, null),
                // ancestor macro, shadowing the command's TIMEOUT
                new ServiceMacroOutput(20, 'TIMEOUT', '10', false, 'template'),
                // the ancestor's check command, lowest priority
                new ServiceMacroOutput(null, 'PORT', '', false, 'command'),
            ],
            $output->macros,
        );
    }

    public function testAnOwnMacroOverridesTheInheritedMacroOfTheSameName(): void
    {
        $this->addTemplate(2, macros: [$this->macro(20, 'timeout', '10')]);
        $this->addTemplate(1, parentId: 2, macros: [$this->macro(10, 'timeout', '30')]);

        $output = $this->provider->provide(new Get(), ['id' => 1]);

        self::assertEquals([new ServiceMacroOutput(10, 'TIMEOUT', '30', false, null)], $output->macros);
    }

    public function testTheOwnCheckCommandWinsOverTheAncestorOne(): void
    {
        $this->addCommand(5, '$USER1$/check -a $_SERVICEFROMTEMPLATE$');
        $this->addCommand(7, '$USER1$/check -a $_SERVICEFROMANCESTOR$');
        $this->addTemplate(2, checkCommandId: 7);
        $this->addTemplate(1, parentId: 2, checkCommandId: 5);

        $output = $this->provider->provide(new Get(), ['id' => 1]);

        self::assertSame(['FROMTEMPLATE'], array_map(static fn (ServiceMacroOutput $macro): string => $macro->name, $output->macros));
    }

    public function testAnUnknownTemplateIsNotFound(): void
    {
        $this->expectException(ServiceTemplateNotFoundException::class);

        $this->provider->provide(new Get(), ['id' => 404]);
    }

    /**
     * @param list<ServiceMacro> $macros
     */
    private function addTemplate(int $id, ?int $parentId = null, ?int $checkCommandId = null, array $macros = []): void
    {
        $this->templates->templates[$id] = new ServiceTemplate(
            new ServiceTemplateId($id),
            new ServiceTemplateName('tpl-' . $id),
            parentId: $parentId !== null ? new ServiceTemplateId($parentId) : null,
            checkCommandId: $checkCommandId !== null ? new CommandId($checkCommandId) : null,
            macros: new Collection($macros, ServiceMacro::class),
        );
    }

    private function addCommand(int $id, string $line): void
    {
        $this->commands->commands[$id] = new Command(
            new CommandId($id),
            new CommandName('cmd-' . $id),
            CommandTypeEnum::Check,
            new CommandLine($line),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: false,
            connector: null,
            comment: null,
        );
    }

    private function macro(int $id, string $name, string $value, bool $isPassword = false): ServiceMacro
    {
        return new ServiceMacro(new ServiceMacroName($name), $value, isPassword: $isPassword, id: new ServiceMacroId($id));
    }
}
