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
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacroParentEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Host\InheritedHostMacros;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Service\InheritedHostMacrosResolver;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostTemplateRepository;

final class InheritedHostMacrosResolverTest extends TestCase
{
    private FakeHostTemplateRepository $templates;

    private FakeCommandRepository $commands;

    private InheritedHostMacrosResolver $resolver;

    protected function setUp(): void
    {
        $this->templates = new FakeHostTemplateRepository();
        $this->commands = new FakeCommandRepository();
        $this->resolver = new InheritedHostMacrosResolver($this->templates, $this->commands);
    }

    public function testItResolvesTheTemplateMacrosOfTheInheritanceLine(): void
    {
        $this->addTemplate(1, macros: [new HostMacro(new HostMacroName('tpl'), 'x', isPassword: false, id: new HostMacroId(10))]);

        $inherited = $this->resolver->resolve($this->templateIds(1), null);

        self::assertSame(HostMacroParentEnum::Template, $inherited->findByName(new HostMacroName('tpl'))?->parent);
        self::assertSame([1], $this->templates->receivedLineTemplateIds);
    }

    public function testTheHostCheckCommandContributesItsHostMacros(): void
    {
        $this->addCommand(5, '$USER1$/check -a $_HOSTFROMHOST$');

        $inherited = $this->resolver->resolve($this->templateIds(), new CommandId(5));

        self::assertSame(['FROMHOST'], $this->names($inherited));
    }

    public function testWithoutAHostCheckCommandTheFirstTemplateCheckCommandIsUsed(): void
    {
        // Legacy getMacros(): no command on the host → the first template of the line having one.
        $this->addCommand(5, '$USER1$/check -a $_FROMFIRST$');
        $this->addCommand(6, '$USER1$/check -a $_HOSTFROMSECOND$');
        $this->addCommand(7, '$USER1$/check -a $_HOSTFROMTEMPLATE$');
        $this->addTemplate(1);
        $this->addTemplate(2, checkCommandId: 7);
        $this->addTemplate(3, checkCommandId: 6);

        $inherited = $this->resolver->resolve($this->templateIds(1, 2, 3), null);

        self::assertSame(['FROMTEMPLATE'], $this->names($inherited));
    }

    public function testTheHostCheckCommandWinsOverTheTemplateOne(): void
    {
        $this->addCommand(5, '$USER1$/check -a $_HOSTFROMHOST$');
        $this->addCommand(7, '$USER1$/check -a $_HOSTFROMTEMPLATE$');
        $this->addTemplate(1, checkCommandId: 7);

        $inherited = $this->resolver->resolve($this->templateIds(1), new CommandId(5));

        self::assertSame(['FROMHOST'], $this->names($inherited));
    }

    public function testANonCheckHostCommandDoesNotFallBackToTheTemplateOne(): void
    {
        // Legacy only falls back when the host has no command at all.
        $this->addCommand(5, '$USER1$/notify -a $_HOSTNOTIFY$', CommandTypeEnum::Notification);
        $this->addCommand(7, '$USER1$/check -a $_HOSTFROMTEMPLATE$');
        $this->addTemplate(1, checkCommandId: 7);

        $inherited = $this->resolver->resolve($this->templateIds(1), new CommandId(5));

        self::assertSame([], $this->names($inherited));
    }

    public function testServiceTemplateCheckCommandsComeAfterTheCheckCommand(): void
    {
        $this->addCommand(5, '$USER1$/check -a $_HOSTSHARED$');
        $this->addCommand(8, '$USER1$/svc -a $_HOSTSHARED$ -b $_HOSTFROMSERVICE$');
        $this->addTemplate(1, serviceTemplateCheckCommandIds: [8]);

        $inherited = $this->resolver->resolve($this->templateIds(1), new CommandId(5));

        self::assertSame(['SHARED', 'FROMSERVICE'], $this->names($inherited));
    }

    public function testServiceTemplateCheckCommandsFollowTheInheritanceLineOrder(): void
    {
        $this->addCommand(8, '$USER1$/svc -a $_HOSTFIRST$');
        $this->addCommand(9, '$USER1$/svc -a $_HOSTSECOND$');
        $this->addTemplate(1, serviceTemplateCheckCommandIds: [8]);
        $this->addTemplate(2, serviceTemplateCheckCommandIds: [9]);

        $inherited = $this->resolver->resolve($this->templateIds(1, 2), null);

        self::assertSame(['FIRST', 'SECOND'], $this->names($inherited));
    }

    public function testATemplateMacroWinsOverAServiceTemplateCommandMacro(): void
    {
        $this->addCommand(8, '$USER1$/svc -a $_HOSTSHARED$');
        $this->addTemplate(1, macros: [new HostMacro(new HostMacroName('shared'), 'tpl', isPassword: false, id: new HostMacroId(10))], serviceTemplateCheckCommandIds: [8]);

        $inherited = $this->resolver->resolve($this->templateIds(1), null);

        self::assertSame(HostMacroParentEnum::Template, $inherited->findByName(new HostMacroName('shared'))?->parent);
    }

    public function testADanglingCommandReferenceContributesNothing(): void
    {
        $this->addTemplate(1, checkCommandId: 404, serviceTemplateCheckCommandIds: [405]);

        self::assertSame([], $this->names($this->resolver->resolve($this->templateIds(1), null)));
    }

    public function testItResolvesAnAlreadyLoadedLineWithoutFetchingItAgain(): void
    {
        $this->addCommand(7, '$USER1$/check -a $_HOSTFROMTEMPLATE$');
        $this->addTemplate(1, macros: [new HostMacro(new HostMacroName('tpl'), 'x', isPassword: false, id: new HostMacroId(10))], checkCommandId: 7);
        $line = [$this->templates->hostTemplates[1]];

        $inherited = $this->resolver->resolveLine($line, null);

        self::assertSame(['TPL', 'FROMTEMPLATE'], $this->names($inherited));
        self::assertSame([], $this->templates->receivedLineTemplateIds);
    }

    /**
     * @param list<HostMacro> $macros
     * @param list<int> $serviceTemplateCheckCommandIds
     */
    private function addTemplate(int $id, array $macros = [], ?int $checkCommandId = null, array $serviceTemplateCheckCommandIds = []): void
    {
        $this->templates->hostTemplates[$id] = new HostTemplate(
            new HostTemplateId($id),
            new HostTemplateName('tpl-' . $id),
            new Collection($macros, HostMacro::class),
            $checkCommandId !== null ? new CommandId($checkCommandId) : null,
            new Collection(array_map(static fn (int $commandId): CommandId => new CommandId($commandId), $serviceTemplateCheckCommandIds), CommandId::class),
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

    /**
     * @return Collection<HostTemplateId>
     */
    private function templateIds(int ...$ids): Collection
    {
        return new Collection(array_map(static fn (int $id): HostTemplateId => new HostTemplateId($id), $ids), HostTemplateId::class);
    }

    /**
     * @return list<string>
     */
    private function names(InheritedHostMacros $inherited): array
    {
        return array_map(static fn (HostMacro $macro): string => $macro->name->value, $inherited->toList());
    }
}
