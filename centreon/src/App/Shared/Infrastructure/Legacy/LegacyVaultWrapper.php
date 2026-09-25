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

namespace App\Shared\Infrastructure\Legacy;

use App\Shared\Domain\VaultInterface;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Application\VaultEligibilityService;
use Webmozart\Assert\Assert;

final readonly class LegacyVaultWrapper implements VaultInterface
{
    /**
     * Extracts the UUID from a `secret::` path: matches `<prefix>/<uuid>::<key>` and captures the
     * UUID (group 2). Transcribed by hand from Core
     * (`Core\Security\Vault\Domain\Model\VaultConfiguration::UUID_EXTRACTION_REGEX`), which App cannot
     * import across the deptrac boundary; kept in sync with Core and guarded by a unit test.
     */
    private const UUID_EXTRACTION_REGEX = '^(.*)\/(.*)::(.*)$';

    /**
     * Resolved per call, never in the constructor: `LegacyContainer` is a `#[Lazy]` proxy that
     * boots a second Symfony kernel on first touch, which would then happen for every consumer of
     * VaultInterface. Same arrangement as LegacyGorgoneNodesSynchronizer.
     */
    public function __construct(
        private LegacyContainer $legacyContainer,
    ) {
    }

    public function isEnabled(string $featureFlag = 'vault'): bool
    {
        return $this->eligibilityService()->shouldUseVault($featureFlag);
    }

    public function read(string $path): array
    {
        return $this->readRepository()->findFromPath($path);
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

        $segments = explode('::', $value);
        $key = end($segments);

        $data = $this->read($value);

        if (! isset($data[$key]) || ! is_string($data[$key])) {
            throw new \RuntimeException(sprintf('Unable to resolve vault credential "%s"', $key));
        }

        return $data[$key];
    }

    public function extractUuid(string $value): ?string
    {
        if (preg_match('/' . self::UUID_EXTRACTION_REGEX . '/', $value, $matches) === 1) {
            return $matches[2];
        }

        return null;
    }

    public function write(string $customPath, string $key, string $value, ?string $uuid = null): string
    {
        return $this->writeMany($customPath, [$key => $value], $uuid)[$key];
    }

    public function writeMany(string $customPath, array $secrets, ?string $uuid = null, array $deletes = []): array
    {
        $writeRepository = $this->writeRepository();
        $writeRepository->setCustomPath($customPath);

        // Core's upsert() addresses deletions by the array keys of its third argument, so the list of
        // keys to drop is turned into a map before it is handed over.
        $paths = $writeRepository->upsert($uuid, $secrets, array_fill_keys($deletes, ''));

        foreach (array_keys($secrets) as $key) {
            if (! isset($paths[$key])) {
                throw new \RuntimeException(sprintf('Unable to write vault credential "%s"', $key));
            }
        }

        // The underlying repository returns a path for every key stored under the UUID (including
        // pre-existing ones when writing to an existing entry); the contract only exposes the keys
        // that were requested, so surplus paths never leak onto the calling resource.
        return array_intersect_key($paths, $secrets);
    }

    public function delete(string $customPath, string $uuid): void
    {
        $writeRepository = $this->writeRepository();
        $writeRepository->setCustomPath($customPath);

        $writeRepository->delete($uuid);
    }

    private function readRepository(): ReadVaultRepositoryInterface
    {
        $repository = $this->legacyContainer->get(ReadVaultRepositoryInterface::class);
        Assert::isInstanceOf($repository, ReadVaultRepositoryInterface::class);

        return $repository;
    }

    private function writeRepository(): WriteVaultRepositoryInterface
    {
        $repository = $this->legacyContainer->get(WriteVaultRepositoryInterface::class);
        Assert::isInstanceOf($repository, WriteVaultRepositoryInterface::class);

        return $repository;
    }

    private function eligibilityService(): VaultEligibilityService
    {
        $service = $this->legacyContainer->get(VaultEligibilityService::class);
        Assert::isInstanceOf($service, VaultEligibilityService::class);

        return $service;
    }
}
