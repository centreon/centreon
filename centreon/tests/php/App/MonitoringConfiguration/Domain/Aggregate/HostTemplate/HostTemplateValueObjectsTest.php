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

use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateAlias;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateDataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateExtendedInformations;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacroId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacroName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateNotifications;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSchedulingOptions;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSnmpCommunity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The value objects hold any value the storage can: the write bounds are not theirs.
 */
final class HostTemplateValueObjectsTest extends TestCase
{
    public function testTheSchedulingOptionsHoldValuesBelowTheWriteBounds(): void
    {
        $options = new HostTemplateSchedulingOptions(maxCheckAttempts: 0, normalCheckInterval: 0, retryCheckInterval: -1);

        self::assertSame([0, 0, -1], [$options->maxCheckAttempts, $options->normalCheckInterval, $options->retryCheckInterval]);
    }

    public function testTheDataProcessingHoldsValuesOutsideTheWriteBounds(): void
    {
        $dataProcessing = new HostTemplateDataProcessing(
            acknowledgmentTimeout: 0,
            freshnessThreshold: -1,
            lowFlapThreshold: -5,
            highFlapThreshold: 150,
        );

        self::assertSame(
            [0, -1, -5, 150],
            [$dataProcessing->acknowledgmentTimeout, $dataProcessing->freshnessThreshold, $dataProcessing->lowFlapThreshold, $dataProcessing->highFlapThreshold],
        );
    }

    public function testTheExtendedInformationsHoldTextsLongerThanTheWriteBounds(): void
    {
        $note = str_repeat('n', 600);
        $url = 'https://example.com/' . str_repeat('u', 2980);

        $informations = new HostTemplateExtendedInformations(noteUrl: $url, note: $note, actionUrl: $url, comment: str_repeat('c', 70000));

        self::assertSame($note, $informations->note);
        self::assertSame($url, $informations->noteUrl);
        self::assertSame($url, $informations->actionUrl);
    }

    public function testTheNotificationsHoldNegativeDelays(): void
    {
        $notifications = new HostTemplateNotifications(interval: -1, firstDelay: -2, recoveryDelay: -3);

        self::assertSame([-1, -2, -3], [$notifications->interval, $notifications->firstDelay, $notifications->recoveryDelay]);
    }

    public function testTheCheckOptionsHoldArgumentsWithoutACommand(): void
    {
        $checkOptions = new HostTemplateCheckOptions(null, [1 => '-w', 3 => '80']);

        self::assertNull($checkOptions->checkCommandId);
        self::assertSame(['-w', '80'], $checkOptions->args);
    }

    /**
     * @param \Closure(): mixed $build
     */
    #[DataProvider('provideStorageViolations')]
    public function testItRefusesWhatTheStorageCannotHold(\Closure $build): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $build();
    }

    /**
     * @return iterable<string, array{\Closure(): mixed}>
     */
    public static function provideStorageViolations(): iterable
    {
        yield 'alias over 200' => [static fn (): HostTemplateAlias => new HostTemplateAlias(str_repeat('a', 201))];

        yield 'blank alias' => [static fn (): HostTemplateAlias => new HostTemplateAlias('  ')];

        yield 'alternative icon text over 200' => [static fn (): HostTemplateExtendedInformations => new HostTemplateExtendedInformations(altIcon: str_repeat('a', 201))];

        yield 'SNMP community over 255' => [static fn (): HostTemplateSnmpCommunity => new HostTemplateSnmpCommunity(str_repeat('s', 256))];

        yield 'blank SNMP community' => [static fn (): HostTemplateSnmpCommunity => new HostTemplateSnmpCommunity(' ')];

        yield 'macro value over 4096' => [static fn (): HostTemplateMacro => new HostTemplateMacro(new HostTemplateMacroName('M'), str_repeat('v', 4097), isPassword: false)];

        yield 'macro name over 255 once wrapped' => [static fn (): HostTemplateMacroName => new HostTemplateMacroName(str_repeat('N', 249))];

        yield 'blank macro name' => [static fn (): HostTemplateMacroName => new HostTemplateMacroName(' ')];

        yield 'macro id 0' => [static fn (): HostTemplateMacroId => new HostTemplateMacroId(0)];

        yield 'check argument with the delimiter' => [static fn (): HostTemplateCheckOptions => new HostTemplateCheckOptions(null, ['a!b'])];

        yield 'check argument with an escape token' => [static fn (): HostTemplateCheckOptions => new HostTemplateCheckOptions(null, ['a#BR#b'])];

        yield 'event handler argument with an escape token' => [static fn (): HostTemplateDataProcessing => new HostTemplateDataProcessing(eventHandlerArgs: ['#T#'])];
    }

    public function testTheBoundariesTheStorageCanHold(): void
    {
        self::assertSame(200, mb_strlen(new HostTemplateAlias(str_repeat('a', 200))->value));
        self::assertSame(255, mb_strlen(new HostTemplateSnmpCommunity(str_repeat('s', 255))->value));
        self::assertSame(4096, mb_strlen(new HostTemplateMacro(new HostTemplateMacroName('M'), str_repeat('v', 4096), isPassword: false)->value));
        self::assertSame('$_HOST' . str_repeat('N', 248) . '$', new HostTemplateMacroName(str_repeat('n', 248))->toStorageName());
        self::assertSame(200, mb_strlen((string) new HostTemplateExtendedInformations(altIcon: str_repeat('a', 200))->altIcon));
    }
}
