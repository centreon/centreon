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

use Monolog\Formatter\LineFormatter;
use Monolog\Processor\UidProcessor;
use Symfony\Bridge\Monolog\Processor\RouteProcessor;
use Symfony\Bridge\Monolog\Processor\TokenProcessor;
use Symfony\Bridge\Monolog\Processor\WebProcessor;
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

    // UidProcessor stamps every record on every channel with one id per
    // process — single `grep <uid>` across all log files.
    $services->set('monolog.processor.uid', UidProcessor::class)
        ->tag('monolog.processor');

    // Request/security context globally — every log file (catch-all and
    // dedicated) gets the HTTP shape when a request is in scope; CLI
    // workers see empty values, never problematic noise.
    $services->set('monolog.processor.web', WebProcessor::class)
        ->tag('monolog.processor');

    $services->set('monolog.processor.route', RouteProcessor::class)
        ->tag('monolog.processor');

    $services->set('monolog.processor.token', TokenProcessor::class)
        ->arg('$tokenStorage', service('security.token_storage'))
        ->tag('monolog.processor');

    // RFC3339 timestamp at service level. NOT at handler level on
    // rotating_file, where `date_format:` configures the FILENAME suffix.
    $services->set('monolog.formatter.line', LineFormatter::class)
        ->arg('$dateFormat', DateTimeInterface::RFC3339);
};
