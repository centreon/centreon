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

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('App\\AiConfiguration\\', __DIR__ . '/../../src/App/AiConfiguration');

    // Provider calls never reach link-local addresses (cloud instance metadata),
    // checked on the address actually connected to, whatever the URL spelling.
    // Private networks stay reachable: self-hosted models live there.
    $services->set('ai_configuration.http_client', NoPrivateNetworkHttpClient::class)
        ->args([service('http_client'), [
            '169.254.0.0/16',
            'fe80::/10',
            'fd00:ec2::254/128', // AWS instance metadata over IPv6
            // 169.254.0.0/16 embedded in IPv6: mapped, compatible, 6to4, NAT64
            '::ffff:a9fe:0/112',
            '::a9fe:0/112',
            '2002:a9fe::/32',
            '64:ff9b::a9fe:0/112',
            '100.100.100.200/32', // Alibaba Cloud instance metadata
        ]]);
};
