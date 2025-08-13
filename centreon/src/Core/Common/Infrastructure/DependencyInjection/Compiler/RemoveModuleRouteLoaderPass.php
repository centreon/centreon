<?php

declare(strict_types=1);

namespace Core\Common\Infrastructure\DependencyInjection\Compiler;

use Core\Common\Infrastructure\Routing\ModuleRouteLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RemoveModuleRouteLoaderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        global $conf_centreon;

        if (! isset($conf_centreon['hostCentreon'])) {
            foreach ($container->getDefinitions() as $id => $definition) {
                $class = $definition->getClass();

                if (!$class) {
                    continue;
                }

                $class = ltrim($class, '\\');

                if (is_subclass_of($class, ModuleRouteLoader::class)) {
                        $container->removeDefinition($id);
                }
            }
        }
    }
}
