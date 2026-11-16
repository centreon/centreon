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

namespace Tests\Core\Metric\Infrastructure\Graph;

use Core\Metric\Infrastructure\Graph\LegacyMetricsDataSource;

it('should keep RRD unless VictoriaMetrics is selected', function (mixed $option, bool $expected): void {
    expect(LegacyMetricsDataSource::isVictoriaMetricsOption($option))->toBe($expected);
})->with([
    'option missing' => [null, false],
    'option missing in a fetchColumn() result' => [false, false],
    'empty' => ['', false],
    'unknown value' => ['influxdb', false],
    'rrd' => ['rrd', false],
    'victoria_metrics' => ['victoria_metrics', true],
]);

it('should read the option from the configuration database', function (mixed $value, bool $expected): void {
    $statement = $this->createMock(\PDOStatement::class);
    $statement->method('fetchColumn')->willReturn($value);
    $db = $this->createMock(\PDO::class);
    $db->expects($this->once())
        ->method('prepare')
        ->with($this->stringContains("'metrics_data_source'"))
        ->willReturn($statement);

    expect(LegacyMetricsDataSource::isVictoriaMetrics($db))->toBe($expected);
})->with([
    'victoria_metrics' => ['victoria_metrics', true],
    'absent' => [false, false],
]);

it('should give the rrdtool path when it can be run', function (): void {
    expect(LegacyMetricsDataSource::checkRrdtool(' /bin/sh '))->toBe('/bin/sh');
});

it('should fail clearly when rrdtool cannot be run', function (mixed $path, string $message): void {
    expect(static fn () => LegacyMetricsDataSource::checkRrdtool($path))
        ->toThrow(\RuntimeException::class, $message);
})->with([
    'missing binary' => ['/nonexistent/rrdtool', 'rrdtool cannot be run (/nonexistent/rrdtool)'],
    'directory' => ['/tmp', 'rrdtool cannot be run (/tmp)'],
    'not executable' => ['/etc/hostname', 'rrdtool cannot be run (/etc/hostname)'],
    'no path' => [null, 'rrdtool cannot be run (no path configured)'],
]);

it('should draw a message as a PNG image', function (): void {
    $png = LegacyMetricsDataSource::renderMessageImage(
        LegacyMetricsDataSource::graphImagesNotAvailableMessage() . ' (métriques)',
        300,
        80
    );

    expect(str_starts_with($png, "\x89PNG\r\n\x1a\n"))->toBeTrue()
        ->and(getimagesizefromstring($png))->toMatchArray([0 => 300, 1 => 80]);
});
