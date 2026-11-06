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
use App\MonitoringConfiguration\Domain\Aggregate\HostAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
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
        yield 'max check attempts 2147483647' => [static fn (): HostTemplate => self::make(scheduling: new SchedulingOptions(maxCheckAttempts: HostTemplate::MAX_INTEGER))];

        yield 'normal check interval 2147483647' => [static fn (): HostTemplate => self::make(scheduling: new SchedulingOptions(normalCheckInterval: HostTemplate::MAX_INTEGER))];

        yield 'retry check interval 2147483647' => [static fn (): HostTemplate => self::make(scheduling: new SchedulingOptions(retryCheckInterval: HostTemplate::MAX_INTEGER))];

        yield 'acknowledgment timeout 2147483647' => [static fn (): HostTemplate => self::make(dataProcessing: new DataProcessing(acknowledgmentTimeout: HostTemplate::MAX_INTEGER))];

        yield 'freshness threshold 2147483647' => [static fn (): HostTemplate => self::make(dataProcessing: new DataProcessing(freshnessThreshold: HostTemplate::MAX_INTEGER))];

        yield 'notification interval 2147483647' => [static fn (): HostTemplate => self::make(notifications: Notifications::default()->with(interval: HostTemplate::MAX_INTEGER))];

        yield 'first delay 2147483647' => [static fn (): HostTemplate => self::make(notifications: Notifications::default()->with(firstDelay: HostTemplate::MAX_INTEGER))];

        yield 'recovery delay 2147483647' => [static fn (): HostTemplate => self::make(notifications: Notifications::default()->with(recoveryDelay: HostTemplate::MAX_INTEGER))];
    }

    /**
     * @return iterable<string, array{\Closure(): HostTemplate}>
     */
    public static function provideValuesOutOfTheBounds(): iterable
    {
        yield 'max check attempts 2147483648' => [static fn (): HostTemplate => self::make(scheduling: new SchedulingOptions(maxCheckAttempts: HostTemplate::MAX_INTEGER + 1))];

        yield 'normal check interval 2147483648' => [static fn (): HostTemplate => self::make(scheduling: new SchedulingOptions(normalCheckInterval: HostTemplate::MAX_INTEGER + 1))];

        yield 'retry check interval 2147483648' => [static fn (): HostTemplate => self::make(scheduling: new SchedulingOptions(retryCheckInterval: HostTemplate::MAX_INTEGER + 1))];

        yield 'acknowledgment timeout 2147483648' => [static fn (): HostTemplate => self::make(dataProcessing: new DataProcessing(acknowledgmentTimeout: HostTemplate::MAX_INTEGER + 1))];

        yield 'freshness threshold 2147483648' => [static fn (): HostTemplate => self::make(dataProcessing: new DataProcessing(freshnessThreshold: HostTemplate::MAX_INTEGER + 1))];

        yield 'notification interval 2147483648' => [static fn (): HostTemplate => self::make(notifications: Notifications::default()->with(interval: HostTemplate::MAX_INTEGER + 1))];

        yield 'first delay 2147483648' => [static fn (): HostTemplate => self::make(notifications: Notifications::default()->with(firstDelay: HostTemplate::MAX_INTEGER + 1))];

        yield 'recovery delay 2147483648' => [static fn (): HostTemplate => self::make(notifications: Notifications::default()->with(recoveryDelay: HostTemplate::MAX_INTEGER + 1))];
    }

    private static function make(
        SchedulingOptions $scheduling = new SchedulingOptions(),
        DataProcessing $dataProcessing = new DataProcessing(),
        ?Notifications $notifications = null,
    ): HostTemplate {
        return HostTemplate::create(
            new HostTemplateName('tpl'),
            new HostAlias('alias'),
            schedulingOptions: $scheduling,
            dataProcessing: $dataProcessing,
            notifications: $notifications,
        );
    }
}
