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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\HostTemplate;

use App\MonitoringConfiguration\Domain\Aggregate\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\ExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Notifications;
use App\MonitoringConfiguration\Domain\Aggregate\SchedulingOptions;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Exception\MissingIdException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HostTemplateCreateTest extends TestCase
{
    public function testItCreatesAnUnlockedTemplateWithoutId(): void
    {
        $template = HostTemplate::create(
            new HostTemplateName('tpl'),
            new HostAlias('A template'),
            new CheckOptions(new CommandId(5), ['-w', '80']),
            parentTemplateIds: new Collection([new HostTemplateId(3)], HostTemplateId::class),
        );

        self::assertSame('tpl', $template->name->value);
        self::assertSame('A template', $template->alias?->value);
        self::assertFalse($template->isLocked);
        self::assertSame(5, $template->checkOptions->checkCommandId?->value);
        self::assertSame(['-w', '80'], $template->checkOptions->args);
        self::assertSame([3], array_map(static fn (HostTemplateId $id): int => $id->value, $template->parentTemplateIds->toArray()));
        $this->expectException(MissingIdException::class);
        $template->id();
    }

    /**
     * @param \Closure(): HostTemplate $create
     */
    #[DataProvider('provideValuesWithinTheBounds')]
    public function testItAcceptsAValueOnTheBound(\Closure $create): void
    {
        $this->expectNotToPerformAssertions();

        $create();
    }

    /**
     * @param \Closure(): HostTemplate $create
     */
    #[DataProvider('provideValuesOutOfTheBounds')]
    public function testItRefusesAValueOutOfTheBounds(\Closure $create): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $create();
    }

    /**
     * @return iterable<string, array{\Closure(): HostTemplate}>
     */
    public static function provideValuesWithinTheBounds(): iterable
    {
        yield 'max check attempts 1' => [static fn (): HostTemplate => self::make(scheduling: new SchedulingOptions(maxCheckAttempts: 1))];

        yield 'normal check interval 1' => [static fn (): HostTemplate => self::make(scheduling: new SchedulingOptions(normalCheckInterval: 1))];

        yield 'retry check interval 1' => [static fn (): HostTemplate => self::make(scheduling: new SchedulingOptions(retryCheckInterval: 1))];

        yield 'max check attempts 2147483647' => [static fn (): HostTemplate => self::make(scheduling: new SchedulingOptions(maxCheckAttempts: HostTemplate::MAX_INTEGER))];

        yield 'acknowledgment timeout 1' => [static fn (): HostTemplate => self::make(dataProcessing: new DataProcessing(acknowledgmentTimeout: 1))];

        yield 'freshness threshold 0' => [static fn (): HostTemplate => self::make(dataProcessing: new DataProcessing(freshnessThreshold: 0))];

        yield 'low flap threshold 0' => [static fn (): HostTemplate => self::make(dataProcessing: new DataProcessing(lowFlapThreshold: 0))];

        yield 'high flap threshold 100' => [static fn (): HostTemplate => self::make(dataProcessing: new DataProcessing(highFlapThreshold: 100))];

        yield 'note 512' => [static fn (): HostTemplate => self::make(informations: new ExtendedInformations(note: str_repeat('n', 512)))];

        yield 'note url 2048' => [static fn (): HostTemplate => self::make(informations: new ExtendedInformations(noteUrl: str_repeat('u', 2048)))];

        yield 'action url 2048' => [static fn (): HostTemplate => self::make(informations: new ExtendedInformations(actionUrl: str_repeat('u', 2048)))];

        yield 'alt icon 200' => [static fn (): HostTemplate => self::make(informations: new ExtendedInformations(altIcon: str_repeat('a', 200)))];

        yield 'comment 65535' => [static fn (): HostTemplate => self::make(informations: new ExtendedInformations(comment: str_repeat('c', 65535)))];

        yield 'notification interval 0' => [static fn (): HostTemplate => self::make(notifications: Notifications::default()->with(interval: 0))];

        yield 'first delay 0' => [static fn (): HostTemplate => self::make(notifications: Notifications::default()->with(firstDelay: 0))];

        yield 'recovery delay 0' => [static fn (): HostTemplate => self::make(notifications: Notifications::default()->with(recoveryDelay: 0))];

        yield 'none alone' => [static fn (): HostTemplate => self::make(notifications: Notifications::default()->with(options: [NotificationOptionEnum::None]))];
    }

    /**
     * @return iterable<string, array{\Closure(): HostTemplate}>
     */
    public static function provideValuesOutOfTheBounds(): iterable
    {
        yield 'max check attempts 0' => [static fn (): HostTemplate => self::make(scheduling: new SchedulingOptions(maxCheckAttempts: 0))];

        yield 'normal check interval 0' => [static fn (): HostTemplate => self::make(scheduling: new SchedulingOptions(normalCheckInterval: 0))];

        yield 'retry check interval 0' => [static fn (): HostTemplate => self::make(scheduling: new SchedulingOptions(retryCheckInterval: 0))];

        yield 'max check attempts 2147483648' => [static fn (): HostTemplate => self::make(scheduling: new SchedulingOptions(maxCheckAttempts: HostTemplate::MAX_INTEGER + 1))];

        yield 'acknowledgment timeout 0' => [static fn (): HostTemplate => self::make(dataProcessing: new DataProcessing(acknowledgmentTimeout: 0))];

        yield 'freshness threshold -1' => [static fn (): HostTemplate => self::make(dataProcessing: new DataProcessing(freshnessThreshold: -1))];

        yield 'low flap threshold -1' => [static fn (): HostTemplate => self::make(dataProcessing: new DataProcessing(lowFlapThreshold: -1))];

        yield 'low flap threshold 101' => [static fn (): HostTemplate => self::make(dataProcessing: new DataProcessing(lowFlapThreshold: 101))];

        yield 'high flap threshold -1' => [static fn (): HostTemplate => self::make(dataProcessing: new DataProcessing(highFlapThreshold: -1))];

        yield 'high flap threshold 101' => [static fn (): HostTemplate => self::make(dataProcessing: new DataProcessing(highFlapThreshold: 101))];

        yield 'note 513' => [static fn (): HostTemplate => self::make(informations: new ExtendedInformations(note: str_repeat('n', 513)))];

        yield 'note url 2049' => [static fn (): HostTemplate => self::make(informations: new ExtendedInformations(noteUrl: str_repeat('u', 2049)))];

        yield 'action url 2049' => [static fn (): HostTemplate => self::make(informations: new ExtendedInformations(actionUrl: str_repeat('u', 2049)))];

        yield 'comment 65536' => [static fn (): HostTemplate => self::make(informations: new ExtendedInformations(comment: str_repeat('c', 65536)))];

        yield 'empty note' => [static fn (): HostTemplate => self::make(informations: new ExtendedInformations(note: ''))];

        yield 'notification interval -1' => [static fn (): HostTemplate => self::make(notifications: Notifications::default()->with(interval: -1))];

        yield 'first delay -1' => [static fn (): HostTemplate => self::make(notifications: Notifications::default()->with(firstDelay: -1))];

        yield 'recovery delay -1' => [static fn (): HostTemplate => self::make(notifications: Notifications::default()->with(recoveryDelay: -1))];

        yield 'none with another option' => [static fn (): HostTemplate => self::make(notifications: Notifications::default()->with(options: [NotificationOptionEnum::Down, NotificationOptionEnum::None]))];

        yield 'arguments without a check command' => [static fn (): HostTemplate => self::make(args: ['-w'])];
    }

    public function testOnlyTheFirstOfTwoMacrosOfOneNameIsKept(): void
    {
        $template = self::make(macroNames: ['PORT', 'port']);

        self::assertCount(1, $template->checkOptions->macros);
    }

    /**
     * @param list<string> $macroNames
     * @param list<string> $args
     */
    private static function make(
        ?ExtendedInformations $informations = null,
        SchedulingOptions $scheduling = new SchedulingOptions(),
        DataProcessing $dataProcessing = new DataProcessing(),
        ?Notifications $notifications = null,
        array $macroNames = [],
        array $args = [],
    ): HostTemplate {
        return HostTemplate::create(
            new HostTemplateName('tpl'),
            new HostAlias('alias'),
            new CheckOptions(
                $args === [] ? new CommandId(1) : null,
                $args,
                array_map(static fn (string $name): HostMacro => new HostMacro(new HostMacroName($name), 'v', false), $macroNames),
            ),
            extendedInformations: $informations,
            schedulingOptions: $scheduling,
            dataProcessing: $dataProcessing,
            notifications: $notifications,
        );
    }
}
