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

use App\Shared\Infrastructure\Legacy\LegacyValidationStatusListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    // SanitizingProcessor is registered explicitly in monolog.php so its
    // execution order can be controlled; keep it out of the autoload tagging
    // to avoid a duplicate `monolog.processor` registration.
    $services->load('App\\Shared\\', __DIR__ . '/../../src/App/Shared')
        ->exclude([
            __DIR__ . '/../../src/App/Shared/Infrastructure/Symfony/Kernel.php',
            __DIR__ . '/../../src/App/Shared/Infrastructure/Logging/SanitizingProcessor.php',
        ]);

    // NameConverterInterface has no autowiring alias; bind the listener to the very
    // converter ApiPlatform is configured with (config.new/packages/api_platform.yaml)
    // so /api/latest validation errors report snake_case property paths.
    $services->set(LegacyValidationStatusListener::class)
        ->arg('$nameConverter', service('serializer.name_converter.camel_case_to_snake_case'));
};
