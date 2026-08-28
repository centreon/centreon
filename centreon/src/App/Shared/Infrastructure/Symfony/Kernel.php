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

namespace App\Shared\Infrastructure\Symfony;

use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Application\Query\AsQueryHandler;
use App\Shared\Domain\Event\AsEventHandler;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    private ?string $configFingerprint = null;

    public function getCacheDir(): string
    {
        return '/var/cache/centreon/symfony.new/' . $this->getConfigFingerprint();
    }

    public function getLogDir(): string
    {
        return '/var/log/centreon';
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 5);
    }

    /**
     * MicroKernelTrait::registerBundles() relies on a private getBundlesPath() method chain
     * inherited from BaseKernel via KernelTrait (symfony/dependency-injection). PHP private-method
     * scoping prevents our getConfigDir() override from affecting that chain in Symfony 8.x,
     * so we redirect bundle loading explicitly.
     */
    public function registerBundles(): iterable
    {
        $bundlesPath = $this->getProjectDir() . '/config.new/bundles.php';
        /** @var array<class-string<\Symfony\Component\HttpKernel\Bundle\BundleInterface>, array<string, bool>> $bundles */
        $bundles = is_file($bundlesPath) ? require $bundlesPath : [];

        foreach ($bundles as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->registerAttributeForAutoconfiguration(AsCommandHandler::class, static function (ChildDefinition $definition): void {
            $definition->addTag('messenger.message_handler', ['bus' => 'command.bus']);
        });

        $container->registerAttributeForAutoconfiguration(AsQueryHandler::class, static function (ChildDefinition $definition): void {
            $definition->addTag('messenger.message_handler', ['bus' => 'query.bus']);
        });

        $container->registerAttributeForAutoconfiguration(AsEventHandler::class, static function (ChildDefinition $definition): void {
            $definition->addTag('messenger.message_handler', ['bus' => 'event.bus']);
        });
    }

    /**
     * @phpstan-ignore method.unused
     */
    private function configureContainer(ContainerConfigurator $container): void
    {
        $configDir = $this->getConfigDir();

        $container->import($configDir . '/{packages}/*.yaml');
        $container->import($configDir . '/{packages}/' . $this->environment . '/*.yaml');
        $container->import($configDir . '/{services}/*.php');
        $container->import($configDir . '/{services}/' . $this->environment . '/*.php');
    }

    private function getConfigDir(): string
    {
        return $this->getProjectDir() . '/config.new';
    }

    private function getConfigFingerprint(): string
    {
        return $this->configFingerprint ??= ConfigFingerprint::ofConfigDir($this->getConfigDir());
    }
}
