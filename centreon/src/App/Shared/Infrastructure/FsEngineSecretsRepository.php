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

use App\Shared\Domain\Exception\EngineSecretsUnavailableException;
use App\Shared\Domain\Repository\EngineSecretsRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class FsEngineSecretsRepository implements EngineSecretsRepository
{
    /** @var array<array-key, mixed>|null */
    private ?array $decoded = null;

    public function __construct(
        #[Autowire(param: 'upgrade.engine_context_path')]
        private readonly string $engineContextPath,
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
        $value = $this->load()[$key] ?? null;

        if (! is_string($value)) {
            throw new EngineSecretsUnavailableException(sprintf('Missing or invalid key "%s" in engine context file.', $key));
        }

        return $value;
    }

    /**
     * Read and decode the engine context file once, then memoize it so the
     * two secrets are not parsed twice per request.
     *
     * @return array<array-key, mixed>
     */
    private function load(): array
    {
        if ($this->decoded !== null) {
            return $this->decoded;
        }

        $content = @file_get_contents($this->engineContextPath);
        if ($content === false) {
            throw new EngineSecretsUnavailableException('Engine context file is not available.');
        }

        try {
            $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new EngineSecretsUnavailableException('Engine context file is not valid.', previous: $exception);
        }

        if (! is_array($data)) {
            throw new EngineSecretsUnavailableException('Engine context file does not contain a valid JSON object.');
        }

        return $this->decoded = $data;
    }
}
