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

namespace Tests\App\MonitoringConfiguration\Domain\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Service\InheritedServiceMacros;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateName;
use App\MonitoringConfiguration\Domain\Service\InheritedServiceMacrosResolver;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceTemplateRepository;

final class InheritedServiceMacrosResolverTest extends TestCase
{
    private FakeServiceTemplateRepository $templates;

    private FakeCommandRepository $commands;

    private InheritedServiceMacrosResolver $resolver;

    protected function setUp(): void
    {
        $this->templates = new FakeServiceTemplateRepository();
        $this->commands = new FakeCommandRepository();
        $this->resolver = new InheritedServiceMacrosResolver($this->templates, $this->commands);
    }

    public function testWithoutTemplateNorCommandNothingIsInherited(): void
    {
        self::assertSame([], $this->describe($this->resolver->resolve(null, null)));
    }

    public function testItResolvesTheTemplateMacrosOfTheWholeChainTheNearestWinning(): void
    {
        $this->addTemplate(1, parentId: 2, macros: [$this->macro(10, 'shared', 'from-nearest')]);
        $this->addTemplate(2, macros: [$this->macro(20, 'shared', 'from-parent'), $this->macro(21, 'parent-only', 'x')]);

        self::assertSame(
            [['SHARED', 'from-nearest', 'template'], ['PARENT-ONLY', 'x', 'template']],
            $this->describe($this->resolver->resolve(new ServiceTemplateId(1), null)),
        );
    }

    public function testTheServiceCheckCommandContributesItsServiceMacros(): void
    {
        $this->addCommand(5, '$USER1$/check -a $_SERVICEFROMSERVICE$ -H $_HOSTNOTASERVICEMACRO$');

        self::assertSame(
            [['FROMSERVICE', '', 'command']],
            $this->describe($this->resolver->resolve(null, new CommandId(5))),
        );
    }

    /**
     * The effective check command: without one of its own, the service runs the first one found up its template
     * chain, whose macros it then inherits.
     */
    public function testWithoutACheckCommandTheFirstOneUpTheChainIsUsed(): void
    {
        $this->addCommand(6, '$USER1$/check -a $_SERVICEFROMPARENT$');
        $this->addCommand(7, '$USER1$/check -a $_SERVICEFROMROOT$');
        $this->addTemplate(1, parentId: 2);
        $this->addTemplate(2, parentId: 3, checkCommandId: 6);
        $this->addTemplate(3, checkCommandId: 7);

        self::assertSame(
            [['FROMPARENT', '', 'command']],
            $this->describe($this->resolver->resolve(new ServiceTemplateId(1), null)),
        );
    }

    /**
     * The nominal service: its own check command, and a template whose macros it still inherits.
     */
    public function testTheServiceCheckCommandWinsOverTheTemplateOneAndTheTemplateMacrosAreStillInherited(): void
    {
        $this->addCommand(5, '$USER1$/check -a $_SERVICEFROMSERVICE$');
        $this->addCommand(7, '$USER1$/check -a $_SERVICEFROMTEMPLATE$');
        $this->addTemplate(1, checkCommandId: 7, macros: [$this->macro(10, 'tpl', 'x')]);

        self::assertSame(
            [['TPL', 'x', 'template'], ['FROMSERVICE', '', 'command']],
            $this->describe($this->resolver->resolve(new ServiceTemplateId(1), new CommandId(5))),
        );
    }

    public function testANonCheckServiceCommandDoesNotFallBackToTheTemplateOne(): void
    {
        $this->addCommand(5, '$USER1$/notify -a $_SERVICENOTIFY$', CommandTypeEnum::Notification);
        $this->addCommand(7, '$USER1$/check -a $_SERVICEFROMTEMPLATE$');
        $this->addTemplate(1, checkCommandId: 7);

        self::assertSame([], $this->describe($this->resolver->resolve(new ServiceTemplateId(1), new CommandId(5))));
    }

    public function testATemplateMacroWinsOverACommandMacro(): void
    {
        $this->addCommand(7, '$USER1$/check -a $_SERVICESHARED$ -b $_SERVICECOMMANDONLY$');
        $this->addTemplate(1, checkCommandId: 7, macros: [$this->macro(10, 'shared', 'tpl')]);

        self::assertSame(
            [['SHARED', 'tpl', 'template'], ['COMMANDONLY', '', 'command']],
            $this->describe($this->resolver->resolve(new ServiceTemplateId(1), null)),
        );
    }

    public function testADanglingCommandReferenceContributesNothing(): void
    {
        $this->addTemplate(1, checkCommandId: 404);

        self::assertSame([], $this->describe($this->resolver->resolve(new ServiceTemplateId(1), null)));
        self::assertSame([], $this->describe($this->resolver->resolve(null, new CommandId(405))));
    }

    /**
     * From a line the caller already loaded: the first template of the line is resolved too, and its check
     * command falls back to the first ancestor's.
     */
    public function testItResolvesAnAlreadyLoadedLine(): void
    {
        $this->addCommand(7, '$USER1$/check -a $_SERVICESHARED$ -b $_SERVICEFROMANCESTOR$');
        $this->addTemplate(1, parentId: 2, macros: [$this->macro(10, 'shared', 'from-nearest')]);
        $this->addTemplate(2, checkCommandId: 7, macros: [$this->macro(20, 'shared', 'from-parent')]);
        /** @var list<ServiceTemplate> $line */
        $line = array_values($this->templates->findInheritanceLine(new ServiceTemplateId(1))->toArray());

        self::assertSame(
            [['SHARED', 'from-nearest', 'template'], ['FROMANCESTOR', '', 'command']],
            $this->describe($this->resolver->resolveLine($line, null)),
        );
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

    private function addCommand(int $id, string $line, CommandTypeEnum $type = CommandTypeEnum::Check): void
    {
        $this->commands->commands[$id] = new Command(
            new CommandId($id),
            new CommandName('cmd-' . $id),
            $type,
            new CommandLine($line),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: false,
            connector: null,
            comment: null,
        );
    }

    private function macro(int $id, string $name, string $value): ServiceMacro
    {
        return new ServiceMacro(new ServiceMacroName($name), $value, isPassword: false, id: new ServiceMacroId($id));
    }

    /**
     * @return list<array{string, string, ?string}> name, value and origin of each inherited macro, in order
     */
    private function describe(InheritedServiceMacros $inherited): array
    {
        return array_map(
            static fn (ServiceMacro $macro): array => [$macro->name->value, $macro->value, $macro->parent?->value],
            $inherited->effectiveWith([]),
        );
    }
}
