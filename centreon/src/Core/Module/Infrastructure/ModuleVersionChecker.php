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

