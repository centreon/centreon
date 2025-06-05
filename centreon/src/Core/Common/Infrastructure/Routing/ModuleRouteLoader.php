<?php

declare(strict_types=1);

namespace Core\Common\Infrastructure\Routing;

use Core\Module\Infrastructure\ModuleVersionChecker;
use Symfony\Bundle\FrameworkBundle\Routing\RouteLoaderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Loader\AttributeFileLoader;
use Symfony\Component\Routing\RouteCollection;

abstract readonly class ModuleRouteLoader implements RouteLoaderInterface
{
    public function __construct(
        #[Autowire(service: 'routing.loader.attribute.file')] private AttributeFileLoader $loader,
        #[Autowire(param: 'kernel.project_dir')] private string $projectDir,
        private ModuleVersionChecker $versionChecker
    ) {
    }

    abstract protected function getModuleName(): string;

    abstract protected function getModuleDirectory(): string;

    final public function __invoke(): RouteCollection
    {
        if($this->versionChecker->hasANewVersionAvailable($this->getModuleName())) {
            return new RouteCollection();
        }

        $moduleDir = $this->projectDir . '/src/' . $this->getModuleDirectory() . '/**/*Controller.php';
        $routes = new RouteCollection();
        foreach ($this->loader->import($moduleDir, 'attribute') as $routeCollection) {
            $routes->addCollection($routeCollection);
        }
        $routes->addPrefix('/{base_uri}api/{version}');
        $routes->addDefaults(['base_uri' => 'centreon/']);
        $routes->addRequirements(['base_uri' => '(.+/)|.{0}']);

        return $routes;
    }
}