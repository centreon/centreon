<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

use DateTimeInterface;
use Monolog\Formatter\LineFormatter;
use Symfony\Bridge\Monolog\Processor\RouteProcessor;
use Symfony\Bridge\Monolog\Processor\TokenProcessor;
use Symfony\Bridge\Monolog\Processor\WebProcessor;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // HTTP / security context processors attached to the channels that
    // benefit from extra request scoping (cf. MON-151077). The same set
    // is applied to `bus`, `request` and `app` so command/query dispatch
    // logs (LoggingMiddleware), HTTP request logs and generic application
    // logs all share a consistent shape.
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

    // Override the default MonologBundle line formatter to emit the line
    // timestamp as RFC3339 (cf. MON-151077). Done at service level so
    // every handler picking `monolog.formatter.line` shares the same
    // timestamp shape, and so we sidestep the rotating_file pitfall
    // where handler-level `date_format:` configures the FILENAME suffix
    // and rejects RFC3339.
    $services->set('monolog.formatter.line', LineFormatter::class)
        ->arg('$dateFormat', DateTimeInterface::RFC3339);
};
