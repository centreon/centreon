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

use Adaptation\Log\Adapter\MonologAdapterResetter;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\UidProcessor;
use Symfony\Bridge\Monolog\Processor\RouteProcessor;
use Symfony\Bridge\Monolog\Processor\TokenProcessor;
use Symfony\Bridge\Monolog\Processor\WebProcessor;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // No channel tag: applies to every logger.
    $services->set('monolog.processor.uid', UidProcessor::class)
        ->tag('monolog.processor');

    $services->set('monolog.processor.web', WebProcessor::class)
        ->tag('monolog.processor', ['channel' => 'bus'])
        ->tag('monolog.processor', ['channel' => 'request'])
        ->tag('monolog.processor', ['channel' => 'app']);

    $services->set('monolog.processor.route', RouteProcessor::class)
        ->tag('monolog.processor', ['channel' => 'bus'])
        ->tag('monolog.processor', ['channel' => 'request'])
        ->tag('monolog.processor', ['channel' => 'app']);

    $services->set('monolog.processor.token', TokenProcessor::class)
        ->arg('$tokenStorage', service('security.token_storage'))
        ->tag('monolog.processor', ['channel' => 'bus'])
        ->tag('monolog.processor', ['channel' => 'request'])
        ->tag('monolog.processor', ['channel' => 'app']);

    // Set the line timestamp to RFC3339 at service level: handler-level
    // date_format would instead configure the rotating_file filename suffix.
    $services->set('monolog.formatter.line', LineFormatter::class)
        ->arg('$dateFormat', DateTimeInterface::RFC3339);

    // Reset MonologAdapter's process-lived static UidProcessor between work units
    // (relevant once a long-running consumer keeps the process alive across messages).
    $services->set(MonologAdapterResetter::class)
        ->tag('kernel.reset', ['method' => 'reset']);
};
