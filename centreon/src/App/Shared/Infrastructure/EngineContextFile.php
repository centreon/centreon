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

namespace App\Shared\Infrastructure;

use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Single place deciding where the engine context file (secret and salt Centreon Engine
 * decrypts credentials with) lives, and how centreon-web creates it.
 *
 * The configured path is used as is. Only the default one moves: when no local engine is
 * required and the engine package did not create its directory, centreon-web keeps the file
 * in its own configuration directory.
 */
final readonly class EngineContextFile
{
    public const DEFAULT_PATH = '/etc/centreon-engine/engine-context.json';
    public const FILE_NAME = 'engine-context.json';
    public const ENV_PATH = 'ENGINE_CONTEXT_PATH';

    /** Feature flag making the local engine optional (Core\Gorgone\...\GorgoneCommandService::FEATURE_FLAG). */
    public const FEATURE_FLAG = 'gorgone_api_commands';

    /** Same mode as centreon.conf.php: never world-readable. */
    private const CREATION_MODE = 0640;

    /**
     * @param string $configuredPath parameter "engine_context_path" (environment variable ENGINE_CONTEXT_PATH)
     * @param string $webConfigurationDirectory configuration directory of centreon-web (/etc/centreon)
     * @param bool $isLocalEngineOptional true when centreon-web runs without a local engine (feature flag on)
     * @param string $defaultPath path the engine package provides
     */
    public function __construct(
        private string $configuredPath,
        private string $webConfigurationDirectory,
        private bool $isLocalEngineOptional,
        private string $defaultPath = self::DEFAULT_PATH,
    ) {
    }

    public function getPath(): string
    {
        $path = $this->configuredPath !== '' ? $this->configuredPath : $this->defaultPath;

        if (
            $path === $this->defaultPath
            && $this->isLocalEngineOptional
            && ! is_dir(\dirname($path))
        ) {
            return rtrim($this->webConfigurationDirectory, '/') . '/' . self::FILE_NAME;
        }

        return $path;
    }

    /**
     * Indicates whether centreon-web creates the file itself when it is missing,
     * instead of expecting the engine package to provide it.
     */
    public function isCreatedOnDemand(): bool
    {
        return $this->isLocalEngineOptional;
    }

    /**
     * Write the content without touching the permissions of an existing file.
     * A file centreon-web has to create is only readable by its owner and group.
     *
     * @throws IOException
     */
    public function write(string $content): void
    {
        $path = $this->getPath();

        if (! $this->isLocalEngineOptional || file_exists($path)) {
            // Historical behaviour: the engine package owns the file and its permissions.
            file_put_contents($path, $content);

            return;
        }

        $previousUmask = umask(0027);

        try {
            $file = @fopen($path, 'x');
        } finally {
            umask($previousUmask);
        }

        if ($file === false) {
            throw new IOException(\sprintf('Unable to create the engine context file "%s".', $path), path: $path);
        }

        try {
            if (! chmod($path, self::CREATION_MODE) || fwrite($file, $content) === false) {
                throw new IOException(\sprintf('Unable to write the engine context file "%s".', $path), path: $path);
            }
        } finally {
            fclose($file);
        }
    }
}
