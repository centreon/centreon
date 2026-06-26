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

use Adaptation\Log\Channel\ModuleLogChannel;
use Adaptation\Log\Exception\LoggerException;

it('keeps the historical file name, ignoring APP_ENV', function (): void {
    $channel = new ModuleLogChannel('license-manager');

    expect($channel->getChannelName())->toBe('license-manager')
        ->and($channel->getLogFileName('prod'))->toBe('license-manager.log')
        ->and($channel->getLogFileName('dev'))->toBe('license-manager.log');
});

it('derives a channel from a historical log file name', function (): void {
    $channel = ModuleLogChannel::fromLogFileName('autodiscovery_job.log');

    expect($channel->getChannelName())->toBe('autodiscovery_job')
        ->and($channel->getLogFileName('prod'))->toBe('autodiscovery_job.log');
});

it('accepts valid slugs', function (string $name): void {
    expect((new ModuleLogChannel($name))->getChannelName())->toBe($name);
})->with(['license-manager', 'autodiscovery_job', 'bam', 'a1', 'a']);

it('rejects invalid slugs that could escape the log directory', function (string $name): void {
    expect(fn (): ModuleLogChannel => new ModuleLogChannel($name))
        ->toThrow(LoggerException::class);
})->with([
    'empty' => [''],
    'uppercase' => ['License-Manager'],
    'path traversal' => ['../etc/passwd'],
    'slash' => ['foo/bar'],
    'dot' => ['foo.bar'],
    'leading dash' => ['-foo'],
    'trailing dash' => ['foo-'],
    'space' => ['foo bar'],
]);
