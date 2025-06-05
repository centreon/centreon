<?php

namespace Core\Module\Infrastructure;

use Core\Module\Application\Repository\ModuleInformationRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ModuleVersionChecker
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')] private string $projectDir,
        private ModuleInformationRepositoryInterface $repository
    ) {
    }

    public function hasANewVersionAvailable(string $moduleName): bool
    {
        $moduleInformation = $this->repository->findByName($moduleName);
        if (! $moduleInformation) {
            throw new \RuntimeException($moduleName . " is not installed");
        }
        $getConfigFileVersion = function() use ($moduleName): string {
            require $this->projectDir . "/www/modules/$moduleName/conf.php";

            return $module_conf[$moduleName]["mod_release"];
        };

        return version_compare($getConfigFileVersion(), $moduleInformation->getVersion(), ">");
    }
}