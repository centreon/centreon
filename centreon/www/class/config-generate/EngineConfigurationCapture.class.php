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

use Core\Gorgone\Application\EngineConfiguration\EngineConfigurationBuilder;

/**
 * Hands the objects the generator writes to the builder of the centreon-engine JSON configuration,
 * while a poller run by centreon-engine is generated (feature flag "engine_structured_configuration").
 */
final class EngineConfigurationCapture
{
    /** Generator classes whose objects go into the document; the others are left out. */
    private const KINDS = [
        'Command' => EngineConfigurationBuilder::KIND_COMMAND,
        'Host' => EngineConfigurationBuilder::KIND_HOST,
        'HostTemplate' => EngineConfigurationBuilder::KIND_HOST_TEMPLATE,
        'Service' => EngineConfigurationBuilder::KIND_SERVICE,
        'ServiceTemplate' => EngineConfigurationBuilder::KIND_SERVICE_TEMPLATE,
        'Timeperiod' => EngineConfigurationBuilder::KIND_TIMEPERIOD,
    ];

    private static ?EngineConfigurationBuilder $builder = null;

    public static function start(EngineConfigurationBuilder $builder): void
    {
        self::$builder = $builder;
    }

    public static function stop(): void
    {
        self::$builder = null;
    }

    public static function isActive(): bool
    {
        return self::$builder !== null;
    }

    /**
     * @param string $class generator class which wrote the object
     * @param array<string, string|list<string>> $written attributes as written in the .cfg file
     */
    public static function record(string $class, array $written): void
    {
        if (self::$builder === null) {
            return;
        }
        if ($class === 'Resource') {
            self::$builder->setResourceMacros($written);
        } elseif ($class === 'Engine') {
            self::$builder->setEngineSettings($written);
        } elseif (isset(self::KINDS[$class])) {
            self::$builder->addObject(self::KINDS[$class], $written);
        }
    }

    /**
     * @param array{downtime_id: int|string, downtime_name?: ?string, timezone?: ?string,
     *     host_ids: array<int|string>, service_ids: array<string>, period: array<string>} $object
     *     a recurring downtime as RecurringDowntime writes it
     */
    public static function recordRecurringDowntime(array $object): void
    {
        self::$builder?->addRecurringDowntime($object);
    }

    /**
     * @param string $name broker_name of a broker module (cbmod) of the poller
     * @param array<mixed> $outputs its outputs
     */
    public static function recordBrokerModule(string $name, array $outputs): void
    {
        self::$builder?->addBrokerModule($name, $outputs);
    }
}
