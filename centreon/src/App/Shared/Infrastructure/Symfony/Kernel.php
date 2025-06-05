<?php

declare(strict_types=1);

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
 */

namespace App\Shared\Infrastructure\Symfony;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getCacheDir(): string
    {
        return '/var/cache/centreon/symfony.new';
    }

    public function getLogDir(): string
    {
        return '/var/log/centreon/symfony.new';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $configDir = $this->getConfigDir();

        $container->import($configDir.'/{packages}/*.yaml');
        $container->import($configDir.'/{packages}/'.$this->environment.'/*.yaml');
        $container->import($configDir.'/{services}/*.php');
        $container->import($configDir.'/{services}/'.$this->environment.'/*.php');
    }

    private function getConfigDir(): string
    {
        return $this->getProjectDir().'/config.new';
    }
}
