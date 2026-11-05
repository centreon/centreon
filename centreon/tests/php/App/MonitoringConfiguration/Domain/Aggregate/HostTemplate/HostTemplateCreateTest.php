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

use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotificationOptionEnum;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSchedulingOptions;
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
            new HostTemplateAlias('A template'),
            new HostTemplateCheckOptions(null, ['-w', '80']),
            parentTemplateIds: new Collection([new HostTemplateId(3)], HostTemplateId::class),
        );

        self::assertSame('tpl', $template->name->value);
        self::assertSame('A template', $template->alias?->value);
        self::assertFalse($template->isLocked);
        self::assertNull($template->checkOptions->checkCommandId);
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
        yield 'max check attempts 1' => [static fn (): HostTemplate => self::make(scheduling: new HostTemplateSchedulingOptions(maxCheckAttempts: 1))];

        yield 'normal check interval 1' => [static fn (): HostTemplate => self::make(scheduling: new HostTemplateSchedulingOptions(normalCheckInterval: 1))];

        yield 'retry check interval 1' => [static fn (): HostTemplate => self::make(scheduling: new HostTemplateSchedulingOptions(retryCheckInterval: 1))];

        yield 'max check attempts 2147483647' => [static fn (): HostTemplate => self::make(scheduling: new HostTemplateSchedulingOptions(maxCheckAttempts: HostTemplate::MAX_INTEGER))];

        yield 'acknowledgment timeout 1' => [static fn (): HostTemplate => self::make(dataProcessing: new HostTemplateDataProcessing(acknowledgmentTimeout: 1))];

        yield 'freshness threshold 0' => [static fn (): HostTemplate => self::make(dataProcessing: new HostTemplateDataProcessing(freshnessThreshold: 0))];

        yield 'low flap threshold 0' => [static fn (): HostTemplate => self::make(dataProcessing: new HostTemplateDataProcessing(lowFlapThreshold: 0))];

        yield 'high flap threshold 100' => [static fn (): HostTemplate => self::make(dataProcessing: new HostTemplateDataProcessing(highFlapThreshold: 100))];

        yield 'note 512' => [static fn (): HostTemplate => self::make(informations: new HostTemplateExtendedInformations(note: str_repeat('n', 512)))];

        yield 'note url 2048' => [static fn (): HostTemplate => self::make(informations: new HostTemplateExtendedInformations(noteUrl: str_repeat('u', 2048)))];

        yield 'action url 2048' => [static fn (): HostTemplate => self::make(informations: new HostTemplateExtendedInformations(actionUrl: str_repeat('u', 2048)))];

        yield 'alt icon 200' => [static fn (): HostTemplate => self::make(informations: new HostTemplateExtendedInformations(altIcon: str_repeat('a', 200)))];

        yield 'comment 65535' => [static fn (): HostTemplate => self::make(informations: new HostTemplateExtendedInformations(comment: str_repeat('c', 65535)))];

        yield 'notification interval 0' => [static fn (): HostTemplate => self::make(notifications: new HostTemplateNotifications(interval: 0))];

        yield 'first delay 0' => [static fn (): HostTemplate => self::make(notifications: new HostTemplateNotifications(firstDelay: 0))];

        yield 'recovery delay 0' => [static fn (): HostTemplate => self::make(notifications: new HostTemplateNotifications(recoveryDelay: 0))];

        yield 'none alone' => [static fn (): HostTemplate => self::make(notifications: new HostTemplateNotifications(options: [HostTemplateNotificationOptionEnum::None]))];
    }

    /**
     * @return iterable<string, array{\Closure(): HostTemplate}>
     */
    public static function provideValuesOutOfTheBounds(): iterable
    {
        yield 'max check attempts 0' => [static fn (): HostTemplate => self::make(scheduling: new HostTemplateSchedulingOptions(maxCheckAttempts: 0))];

        yield 'normal check interval 0' => [static fn (): HostTemplate => self::make(scheduling: new HostTemplateSchedulingOptions(normalCheckInterval: 0))];

        yield 'retry check interval 0' => [static fn (): HostTemplate => self::make(scheduling: new HostTemplateSchedulingOptions(retryCheckInterval: 0))];

        yield 'max check attempts 2147483648' => [static fn (): HostTemplate => self::make(scheduling: new HostTemplateSchedulingOptions(maxCheckAttempts: HostTemplate::MAX_INTEGER + 1))];

        yield 'acknowledgment timeout 0' => [static fn (): HostTemplate => self::make(dataProcessing: new HostTemplateDataProcessing(acknowledgmentTimeout: 0))];

        yield 'freshness threshold -1' => [static fn (): HostTemplate => self::make(dataProcessing: new HostTemplateDataProcessing(freshnessThreshold: -1))];

        yield 'low flap threshold -1' => [static fn (): HostTemplate => self::make(dataProcessing: new HostTemplateDataProcessing(lowFlapThreshold: -1))];

        yield 'low flap threshold 101' => [static fn (): HostTemplate => self::make(dataProcessing: new HostTemplateDataProcessing(lowFlapThreshold: 101))];

        yield 'high flap threshold -1' => [static fn (): HostTemplate => self::make(dataProcessing: new HostTemplateDataProcessing(highFlapThreshold: -1))];

        yield 'high flap threshold 101' => [static fn (): HostTemplate => self::make(dataProcessing: new HostTemplateDataProcessing(highFlapThreshold: 101))];

        yield 'note 513' => [static fn (): HostTemplate => self::make(informations: new HostTemplateExtendedInformations(note: str_repeat('n', 513)))];

        yield 'note url 2049' => [static fn (): HostTemplate => self::make(informations: new HostTemplateExtendedInformations(noteUrl: str_repeat('u', 2049)))];

        yield 'action url 2049' => [static fn (): HostTemplate => self::make(informations: new HostTemplateExtendedInformations(actionUrl: str_repeat('u', 2049)))];

        yield 'comment 65536' => [static fn (): HostTemplate => self::make(informations: new HostTemplateExtendedInformations(comment: str_repeat('c', 65536)))];

        yield 'empty note' => [static fn (): HostTemplate => self::make(informations: new HostTemplateExtendedInformations(note: ''))];

        yield 'notification interval -1' => [static fn (): HostTemplate => self::make(notifications: new HostTemplateNotifications(interval: -1))];

        yield 'first delay -1' => [static fn (): HostTemplate => self::make(notifications: new HostTemplateNotifications(firstDelay: -1))];

        yield 'recovery delay -1' => [static fn (): HostTemplate => self::make(notifications: new HostTemplateNotifications(recoveryDelay: -1))];

        yield 'none with another option' => [static fn (): HostTemplate => self::make(notifications: new HostTemplateNotifications(options: [HostTemplateNotificationOptionEnum::Down, HostTemplateNotificationOptionEnum::None]))];

        yield 'two macros of one name' => [static fn (): HostTemplate => self::make(macroNames: ['PORT', 'port'])];
    }

    public function testArgumentsWithoutACommandAreAccepted(): void
    {
        $template = self::make(args: ['-w']);

        self::assertNull($template->checkOptions->checkCommandId);
        self::assertSame(['-w'], $template->checkOptions->args);
    }

    /**
     * @param list<string> $macroNames
     * @param list<string> $args
     */
    private static function make(
        ?HostTemplateExtendedInformations $informations = null,
        HostTemplateSchedulingOptions $scheduling = new HostTemplateSchedulingOptions(),
        HostTemplateDataProcessing $dataProcessing = new HostTemplateDataProcessing(),
        ?HostTemplateNotifications $notifications = null,
        array $macroNames = [],
        array $args = [],
    ): HostTemplate {
        return HostTemplate::create(
            new HostTemplateName('tpl'),
            new HostTemplateAlias('alias'),
            new HostTemplateCheckOptions(
                $args === [] ? new CommandId(1) : null,
                $args,
                new Collection(
                    array_map(static fn (string $name): HostTemplateMacro => new HostTemplateMacro(new HostTemplateMacroName($name), 'v', false), $macroNames),
                    HostTemplateMacro::class,
                ),
            ),
            extendedInformations: $informations,
            schedulingOptions: $scheduling,
            dataProcessing: $dataProcessing,
            notifications: $notifications,
        );
    }
}
