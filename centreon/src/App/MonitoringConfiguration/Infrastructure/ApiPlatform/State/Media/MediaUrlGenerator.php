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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Media;

use App\MonitoringConfiguration\Domain\Aggregate\Media\Media;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Shared with any other resource that exposes a media by reference (e.g. a host's icon), so the
 * URL-building rule lives in exactly one place.
 */
final readonly class MediaUrlGenerator
{
    public function __construct(
        #[Autowire('%shared.media_img_folder_path%')]
        private string $imgFolderPath,
        private RequestStack $requestStack,
    ) {
    }

    public function generate(Media $media): string
    {
        return $this->basePath() . $this->imgFolderPath
            . rawurlencode($media->directory->value) . '/' . rawurlencode($media->name->value);
    }

    /**
     * Root-relative on purpose (no scheme/host): the caller renders this straight into an
     * `<img src>` in the same app, so a relative URL resolves identically while staying agnostic
     * of scheme and host (reverse proxies, http/https). `api/index.php` fakes SCRIPT_NAME/PHP_SELF
     * to the platform's mount path before booting the kernel, so getBasePath() already resolves it
     * correctly here — unlike {@see \App\MonitoringConfiguration\Infrastructure\CentralUrlFactory},
     * which additionally has to support a legacy entry point that boots no kernel at all.
     */
    private function basePath(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (! $request instanceof Request) {
            throw new \RuntimeException('Unable to build a media URL: no current request available.');
        }

        return $request->getBasePath();
    }
}
