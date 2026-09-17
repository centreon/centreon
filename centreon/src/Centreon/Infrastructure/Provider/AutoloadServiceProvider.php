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

namespace Centreon\Infrastructure\Provider;

use Exception;
use Pimple\Container;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * Register all service providers
 */
class AutoloadServiceProvider
{
    public const ERR_TWICE_LOADED = 2001;

    /**
     * Register service providers
     *
     * @param Container $dependencyInjector
     * @return void
     */
    public static function register(Container $dependencyInjector): void
    {
        $providers = self::getProviders($dependencyInjector['finder']);

        foreach ($providers as $provider) {
            $dependencyInjector->register(new $provider());
        }
    }

    /**
     * Get a list of the service provider classes
     *
     * @param Finder $finder
     * @return array
     */
    private static function getProviders(Finder $finder): array
    {
        $providers = [];
        $serviceProviders = $finder
            ->files()
            ->name('ServiceProvider.php')
            ->depth('== 1')
            ->in(__DIR__ . '/../../../../src');

        foreach ($serviceProviders as $serviceProvider) {
            $serviceProviderRelativePath = $serviceProvider->getRelativePath();

            $object = "{$serviceProviderRelativePath}\\ServiceProvider";

            if (! class_exists($object)) {
                continue;
            }

            self::addProvider($providers, $object);
        }

        asort($providers);

        return array_keys($providers);
    }

    /**
     * Add classes only implement the interface AutoloadServiceProviderInterface
     *
     * @param array $providers
     * @param string $object
     * @throws Exception
     * @return void
     */
    private static function addProvider(array &$providers, string $object): void
    {
        if (array_key_exists($object, $providers)) {
            throw new Exception(sprintf('Provider %s is loaded', $object), static::ERR_TWICE_LOADED);
        }

        $interface = AutoloadServiceProviderInterface::class;
        $hasInterface = (new ReflectionClass($object))
            ->implementsInterface($interface);

        if ($hasInterface) {
            $providers[$object] = $object::order();
        }
    }
}
