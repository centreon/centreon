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

use Adaptation\Log\Enum\LogChannelEnum;

it('exposes the file slug for every channel', function (LogChannelEnum $channel, string $expected): void {
    expect($channel->getLogFileSlug())->toBe($expected);
})->with([
    'authentication writes to access' => [LogChannelEnum::AUTHENTICATION, 'access'],
    'password writes to password' => [LogChannelEnum::PASSWORD, 'password'],
    'plugin-pack-manager writes to plugin-pack-manager' => [LogChannelEnum::PLUGIN_PACK_MANAGER, 'plugin-pack-manager'],
    'token writes to token' => [LogChannelEnum::TOKEN, 'token'],
    'upgrade writes to upgrade' => [LogChannelEnum::UPGRADE, 'upgrade'],
    'web writes to web' => [LogChannelEnum::WEB, 'web'],
]);

it('exposes its channel name', function (): void {
    expect(LogChannelEnum::WEB->getChannelName())->toBe('web')
        ->and(LogChannelEnum::AUTHENTICATION->getChannelName())->toBe('authentication');
});

it('prefixes the log file name with APP_ENV', function (): void {
    expect(LogChannelEnum::WEB->getLogFileName('prod'))->toBe('prod.web.log')
        ->and(LogChannelEnum::AUTHENTICATION->getLogFileName('dev'))->toBe('dev.access.log');
});
