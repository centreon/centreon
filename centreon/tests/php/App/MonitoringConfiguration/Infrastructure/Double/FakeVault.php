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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Double;

use App\Shared\Domain\VaultInterface;

/**
 * Configurable in-memory {@see VaultInterface} test double.
 *
 * `isVaultPath()` uses the real prefix check. `resolve()`/`write()` are driven by the public
 * maps/flags below so tests can exercise the happy path and the fail-and-rollback path.
 */
final class FakeVault implements VaultInterface
{
    /** @var array<string, string> vault path => plaintext returned by resolve() */
    public array $resolved = [];

    /** @var array<string, string> credential key => `secret::` path returned by write() */
    public array $writtenPaths = [];

    /** @var list<array{customPath: string, key: string, value: string, uuid: ?string}> */
    public array $writeCalls = [];

    public bool $resolveThrows = false;

    public bool $writeThrows = false;

    public bool $vaultEnabled = true;

    public function isEnabled(string $featureFlag = 'vault'): bool
    {
        return $this->vaultEnabled;
    }

    public function read(string $path): array
    {
        return [];
    }

    public function isVaultPath(string $value): bool
    {
        return str_starts_with($value, self::VAULT_PATH_PREFIX);
    }

    public function resolve(string $value): string
    {
        if (! $this->isVaultPath($value)) {
            return $value;
        }

        if ($this->resolveThrows) {
            throw new \RuntimeException('Unable to resolve vault credential');
        }

        if (! isset($this->resolved[$value])) {
            throw new \RuntimeException(sprintf('No resolved value configured for "%s"', $value));
        }

        return $this->resolved[$value];
    }

    public function write(string $customPath, string $key, string $value, ?string $uuid = null): string
    {
        $this->writeCalls[] = [
            'customPath' => $customPath,
            'key' => $key,
            'value' => $value,
            'uuid' => $uuid,
        ];

        if ($this->writeThrows) {
            throw new \RuntimeException('Unable to write vault credential');
        }

        return $this->writtenPaths[$key] ?? sprintf('secret::vault::%s/new-uuid::%s', $customPath, $key);
    }
}
