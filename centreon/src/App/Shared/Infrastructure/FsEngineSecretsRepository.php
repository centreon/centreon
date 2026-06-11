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

use App\Shared\Domain\Repository\EngineSecretsRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class FsEngineSecretsRepository implements EngineSecretsRepository
{
    public function __construct(
        #[Autowire(param: 'upgrade.engine_context_path')]
        private string $engineContextPath,
    ) {
    }

    public function getAppSecret(): string
    {
        return $this->readKey('app_secret');
    }

    public function getSalt(): string
    {
        return $this->readKey('salt');
    }

    private function readKey(string $key): string
    {
        $content = file_get_contents($this->engineContextPath);
        if ($content === false) {
            throw new \RuntimeException(
                sprintf('Cannot read engine context file: %s', $this->engineContextPath)
            );
        }
        $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new \JsonException('Engine context file does not contain a valid JSON object.');
        }

        $value = $data[$key] ?? null;

        if (! is_string($value)) {
            throw new \JsonException(sprintf('Missing or invalid key "%s" in engine context file.', $key));
        }

        return $value;
    }
}
