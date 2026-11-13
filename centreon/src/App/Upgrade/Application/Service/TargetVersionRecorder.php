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

namespace App\Upgrade\Application\Service;

use App\Upgrade\Domain\Exception\LegacyUpdatesPendingException;
use App\Upgrade\Domain\Exception\PlatformVersionUnknownException;
use App\Upgrade\Domain\Model\UpgradeTargetVersion;
use App\Upgrade\Domain\Repository\UpdateRepository;
use App\Upgrade\Domain\Repository\UpdateScriptFinder;
use App\Upgrade\Domain\Service\UpgradeLoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reads the legacy version and records the target one once the Centreon Web migration sets ran.
 */
final readonly class TargetVersionRecorder
{
    public function __construct(
        private UpdateRepository $updateRepository,
        private UpdateScriptFinder $updateScriptFinder,
        #[Autowire(param: 'centreon.version')]
        private string $platformVersion,
        private UpgradeLoggerInterface $upgradeLogger,
    ) {
    }

    /**
     * Refused while legacy scripts are pending: the Centreon Web migrations expect their schema.
     *
     * @throws LegacyUpdatesPendingException
     * @throws PlatformVersionUnknownException
     *
     * @return string the version the legacy scripts reached
     */
    public function legacyVersion(): string
    {
        $legacyVersion = $this->updateRepository->findCurrentVersion();
        if ($legacyVersion === null || trim($legacyVersion) === '') {
            throw new PlatformVersionUnknownException();
        }

        $pending = $this->updateScriptFinder->findOrderedAvailableUpdates($legacyVersion);
        if ($pending !== []) {
            throw new LegacyUpdatesPendingException(array_values($pending));
        }

        return $legacyVersion;
    }

    /**
     * @param string $legacyVersion the version reached by the legacy scripts
     */
    public function resolve(string $legacyVersion): string
    {
        // Without the install directory, a pending Update-next.php cannot be detected: keep the legacy version.
        if (! $this->updateRepository->installDirectoryExists()) {
            return $legacyVersion;
        }

        return UpgradeTargetVersion::resolve($this->platformVersion, $legacyVersion, $this->updateScriptFinder->hasPendingNextUpdate());
    }

    /**
     * Call once the Centreon Web migration sets ran.
     *
     * @param string $legacyVersion the version the legacy scripts reached and already recorded
     *
     * @return string|null the warning logged when www/install is gone and the version is kept behind the installed code
     */
    public function record(string $legacyVersion): ?string
    {
        if (! $this->updateRepository->installDirectoryExists()) {
            if (! version_compare($this->platformVersion, $legacyVersion, '>')) {
                return null;
            }

            $warning = sprintf('Kept the platform version %s: without the install directory, a pending Update-next.php cannot be detected.', $legacyVersion);
            $this->upgradeLogger->warning($this->platformVersion, $warning);

            return $warning;
        }

        $targetVersion = $this->resolve($legacyVersion);
        if ($targetVersion !== $legacyVersion) {
            $this->updateRepository->updateVersionInformation($targetVersion);
            $this->upgradeLogger->info($targetVersion, sprintf('Recorded the platform version %s, previously %s', $targetVersion, $legacyVersion));
        }

        return null;
    }
}
