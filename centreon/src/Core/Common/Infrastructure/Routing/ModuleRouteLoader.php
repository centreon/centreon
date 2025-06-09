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

namespace Core\Common\Infrastructure\Routing;

use Centreon\Domain\Log\LoggerTrait;
use Core\Module\Infrastructure\ModuleInstallationVerifier;
use Symfony\Bundle\FrameworkBundle\Routing\RouteLoaderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Loader\AttributeFileLoader;
use Symfony\Component\Routing\RouteCollection;

abstract readonly class ModuleRouteLoader implements RouteLoaderInterface
{
    use LoggerTrait;

    public function __construct(
        #[Autowire(service: 'routing.loader.attribute.file')]
        private AttributeFileLoader $loader,
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
        private ModuleInstallationVerifier $installationVerifier
    ) {
    }

    final public function __invoke(): RouteCollection
    {
        try {
            if (! $this->installationVerifier->isInstallComplete($this->getModuleName())) {
                return new RouteCollection();
            }
        } catch(\Throwable $ex) {
            $this->error(
                'Unable to check module installation',
                [
                    'module_name' => $this->getModuleName(),
                    'exception' => $ex,
                ]
            );
        }

        $controllerFilePattern = $this->projectDir . '/src/' . $this->getModuleDirectory() . '/**/*Controller.php';
        $routes = new RouteCollection();
        $routeCollections = $this->loader->import($controllerFilePattern, 'attribute');
        if (! is_array($routeCollections)) {
            return $routes;
        }
        foreach ($routeCollections as $routeCollection) {
            $routes->addCollection($routeCollection);
        }
        $routes->addPrefix('/{base_uri}api/{version}');
        $routes->addDefaults(['base_uri' => 'centreon/']);
        $routes->addRequirements(['base_uri' => '(.+/)|.{0}']);

        return $routes;
    }

    abstract protected function getModuleName(): string;

    abstract protected function getModuleDirectory(): string;
}

