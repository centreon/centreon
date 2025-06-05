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

namespace App\Shared\Infrastructure\Legacy;

use App\Kernel as LegacyKernel;
use App\Shared\Infrastructure\Symfony\Kernel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final readonly class LegacyKernelWrapper implements HttpKernelInterface
{
    public function __construct(
        #[Autowire(service: Kernel::class)]
        private KernelInterface $kernel,
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
    ) {
    }

    public function handle(Request $request, int $type = 1, bool $catch = true): Response
    {
        // make the current container available in the legacy
        $newContainer = $this->kernel->getContainer();
        global $newContainer;

        // bootstrap the legacy app
        require_once $this->projectDir.'/config/bootstrap.php';
        $legacyKernel = new LegacyKernel($this->kernel->getEnvironment(), $this->kernel->isDebug());

        // handle the request by the legacy
        $legacyResponse = $legacyKernel->handle($request, $type, $catch);
        $legacyKernel->terminate($request, $legacyResponse);

        return $legacyResponse;
    }
}
