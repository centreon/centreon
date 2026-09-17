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

namespace App\Upgrade\Infrastructure\FileSystem;

use App\Upgrade\Application\EngineContextWriter;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Writes the engine context JSON file if it is empty or missing.
 *
 * The file contains two keys used by the monitoring engine for encryption:
 *   - app_secret: the Symfony application secret
 *   - salt: a randomly generated value
 *
 * Format: {"app_secret":"<value>","salt":"<value>"}
 */
final readonly class FileSystemEngineContextWriter implements EngineContextWriter
{
    public function __construct(
        #[Autowire(param: 'upgrade.engine_context_path')]
        private string $engineContextPath,
        #[Autowire(param: 'kernel.secret')]
        private string $appSecret,
        private Filesystem $filesystem,
        private LoggerInterface $logger,
    ) {
    }

    public function writeIfMissing(): void
    {
        if ($this->hasContent()) {
            $this->logger->info('Engine context file already has content, skipping write');

            return;
        }

        $this->logger->info('Writing engine context configuration', [
            'path' => $this->engineContextPath,
        ]);

        $content = json_encode([
            'app_secret' => $this->appSecret,
            'salt' => bin2hex(random_bytes(32)),
        ], JSON_THROW_ON_ERROR);

        // Do not use Filesystem::dumpFile — it overrides the file permissions.
        file_put_contents($this->engineContextPath, $content);
    }

    private function hasContent(): bool
    {
        try {
            return ! empty($this->filesystem->readFile($this->engineContextPath));
        } catch (\Throwable) {
            return false;
        }
    }
}
