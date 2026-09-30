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

use App\MonitoringConfiguration\Domain\Service\GorgoneNodesSynchronizer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeGorgoneNodesSynchronizer;

/*
 * Test-env service bindings for the App\MonitoringConfiguration module.
 * New App\MonitoringConfiguration test doubles belong here.
 */
return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Without this double, every poller creation in the API test suite boots the legacy
    // kernel and opens a real connection to the Gorgone API. The failure is swallowed by
    // design, so the suite stays green while depending on an out-of-process host.
    $services->set(GorgoneNodesSynchronizer::class, FakeGorgoneNodesSynchronizer::class)
        ->public();
};
