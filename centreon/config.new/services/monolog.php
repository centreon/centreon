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
use App\Shared\Infrastructure\Logging\PayloadSanitizer;
use App\Shared\Infrastructure\Logging\SanitizingProcessor;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\UidProcessor;
use Symfony\Bridge\Monolog\Processor\RouteProcessor;
use Symfony\Bridge\Monolog\Processor\TokenProcessor;
use Symfony\Bridge\Monolog\Processor\WebProcessor;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Redaction processor. Registered FIRST on purpose: the MonologBundle
    // pushes processors in definition order and Monolog's pushProcessor is
    // LIFO, so the first-registered one runs LAST — after WebProcessor & co.
    // have filled `extra` (the request URL lives in `extra.url`). The bundle
    // ignores the tag `priority` for processors, so ordering is controlled by
    // registration position here.
    $services->set('monolog.processor.sanitizing', SanitizingProcessor::class)
        ->arg('$sanitizer', service(PayloadSanitizer::class))
        ->tag('monolog.processor');

    // Platform processors, registered globally (no channel tag): every logger —
    // catch-all and dedicated — carries the same enriched record shape.
    $services->set('monolog.processor.uid', UidProcessor::class)
        ->tag('monolog.processor');

    $services->set('monolog.processor.web', WebProcessor::class)
        ->tag('monolog.processor');

    $services->set('monolog.processor.route', RouteProcessor::class)
        ->tag('monolog.processor');

    $services->set('monolog.processor.token', TokenProcessor::class)
        ->arg('$tokenStorage', service('security.token_storage'))
        ->tag('monolog.processor');

    // Set the line timestamp to RFC3339 at service level: handler-level
    // date_format would instead configure the rotating_file filename suffix.
    $services->set('monolog.formatter.line', LineFormatter::class)
        ->arg('$dateFormat', DateTimeInterface::RFC3339);

    // Reset MonologAdapter's process-lived static UidProcessor between work units
    // (relevant once a long-running consumer keeps the process alive across messages).
    $services->set(MonologAdapterResetter::class)
        ->tag('kernel.reset', ['method' => 'reset']);
};
