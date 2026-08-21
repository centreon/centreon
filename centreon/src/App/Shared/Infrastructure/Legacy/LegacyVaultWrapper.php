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
    private ReadVaultRepositoryInterface $readRepository;

    private WriteVaultRepositoryInterface $writeRepository;

    private VaultEligibilityService $eligibilityService;

    public function __construct(LegacyContainer $legacyContainer)
    {
        $readRepository = $legacyContainer->get(ReadVaultRepositoryInterface::class);
        Assert::isInstanceOf($readRepository, ReadVaultRepositoryInterface::class);

        $writeRepository = $legacyContainer->get(WriteVaultRepositoryInterface::class);
        Assert::isInstanceOf($writeRepository, WriteVaultRepositoryInterface::class);

        $eligibilityService = $legacyContainer->get(VaultEligibilityService::class);
        Assert::isInstanceOf($eligibilityService, VaultEligibilityService::class);

        $this->readRepository = $readRepository;
        $this->writeRepository = $writeRepository;
        $this->eligibilityService = $eligibilityService;
    }

    public function isEnabled(string $featureFlag = 'vault'): bool
    {
        return $this->eligibilityService->shouldUseVault($featureFlag);
    }

    public function read(string $path): array
    {
        return $this->readRepository->findFromPath($path);
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

    public function write(string $customPath, string $key, string $value, ?string $uuid = null): string
    {
        $this->writeRepository->setCustomPath($customPath);
        $paths = $this->writeRepository->upsert($uuid, [$key => $value], []);

        if (! isset($paths[$key])) {
            throw new \RuntimeException(sprintf('Unable to write vault credential "%s"', $key));
        }

        return $paths[$key];
    }
}
