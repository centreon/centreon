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

namespace Tests\CentreonRemote\Domain\Resources\RemoteConfig;

use CentreonRemote\Domain\Resources\RemoteConfig\CfgNagios;

beforeEach(function (): void {
    $this->originalEnv = $_ENV['IS_CLOUD_PLATFORM'] ?? null;
});

afterEach(function (): void {
    if ($this->originalEnv === null) {
        unset($_ENV['IS_CLOUD_PLATFORM']);
    } else {
        $_ENV['IS_CLOUD_PLATFORM'] = $this->originalEnv;
    }
});

test('it disables flap and host-down checks by default on-prem', function (): void {
    unset($_ENV['IS_CLOUD_PLATFORM']);

    $config = CfgNagios::getConfiguration('TestPoller', 1);

    expect($config['enable_flap_detection'])->toBe('0')
        ->and($config['host_down_disable_service_checks'])->toBe('0');
});

test('it enables flap and host-down checks on cloud', function (): void {
    $_ENV['IS_CLOUD_PLATFORM'] = '1';

    $config = CfgNagios::getConfiguration('TestPoller', 1);

    expect($config['enable_flap_detection'])->toBe('1')
        ->and($config['host_down_disable_service_checks'])->toBe('1');
});
