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

namespace Tests\Centreon\Infrastructure\Monitoring\Metric;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Monitoring\Metric\Interfaces\MetricRepositoryInterface;
use Centreon\Domain\Monitoring\Service;
use Centreon\Infrastructure\Monitoring\Metric\MetricRepositorySelector;
use Core\Metric\Application\Repository\ReadMetricsStorageSettingsRepositoryInterface;
use Core\Metric\Domain\Model\MetricsDataSource;
use Core\Metric\Domain\Model\MetricsStorageSettings;

beforeEach(function (): void {
    $this->built = [];
    $this->rrd = $this->createMock(MetricRepositoryInterface::class);
    $this->victoriaMetrics = $this->createMock(MetricRepositoryInterface::class);
    $this->settingsRepository = $this->createMock(ReadMetricsStorageSettingsRepositoryInterface::class);
    $this->createSelector = fn (): MetricRepositorySelector => new MetricRepositorySelector(
        $this->settingsRepository,
        function (): MetricRepositoryInterface {
            $this->built[] = 'rrd';

            return $this->rrd;
        },
        function (): MetricRepositoryInterface {
            $this->built[] = 'victoria_metrics';

            return $this->victoriaMetrics;
        },
    );
    $this->start = new \DateTime('@1700000000');
    $this->end = new \DateTime('@1700003600');
});

it('should keep the RRD files by default', function (?string $option): void {
    $this->settingsRepository->method('find')
        ->willReturn(new MetricsStorageSettings(MetricsDataSource::fromOption($option), 'http://vm:8428'));
    $this->rrd->expects($this->once())->method('findMetricsByService')->willReturn(['times' => []]);
    $this->victoriaMetrics->expects($this->never())->method('findMetricsByService');

    $result = ($this->createSelector)()->findMetricsByService(new Service(), $this->start, $this->end);

    expect($result)->toBe(['times' => []])
        ->and($this->built)->toBe(['rrd']);
})->with([
    'option missing (existing installation)' => [null],
    'empty option' => [''],
    'unknown value' => ['influxdb'],
    'rrd' => ['rrd'],
]);

it('should read VictoriaMetrics when selected, with the user set before', function (): void {
    $this->settingsRepository->method('find')
        ->willReturn(new MetricsStorageSettings(MetricsDataSource::VictoriaMetrics, 'http://vm:8428'));
    $contact = (new Contact())->setId(3);
    $this->victoriaMetrics->expects($this->once())->method('setContact')->with($contact);
    $this->victoriaMetrics->expects($this->exactly(2))->method('findStatusByService')->willReturn(['ok' => []]);

    $selector = ($this->createSelector)();
    $selector->setContact($contact);
    $selector->findStatusByService(new Service(), $this->start, $this->end);
    $selector->findStatusByService(new Service(), $this->start, $this->end);

    expect($this->built)->toBe(['victoria_metrics']);
});

it('should not build any repository before the first read', function (): void {
    $this->settingsRepository->expects($this->never())->method('find');

    ($this->createSelector)()->setContact(new Contact());

    expect($this->built)->toBe([]);
});
