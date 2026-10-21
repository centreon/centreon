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

namespace App\Upgrade\Application;

use App\Upgrade\Domain\Repository\UpdateRepository;
use App\Upgrade\Domain\Repository\UpdateScriptFinder;
use App\Upgrade\Domain\UpgradeTargetVersion;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The version the platform reaches once its legacy scripts and its core migration sets ran.
 */
final readonly class TargetVersionRecorder
{
    public function __construct(
        private UpdateRepository $updateRepository,
        private UpdateScriptFinder $updateScriptFinder,
        #[Autowire(param: 'centreon.version')]
        private string $platformVersion,
    ) {
    }

    /**
     * @param string $legacyVersion the version reached by the legacy scripts
     */
    public function resolve(string $legacyVersion): string
    {
        return UpgradeTargetVersion::resolve($this->platformVersion, $legacyVersion, $this->updateScriptFinder->hasPendingNextUpdate());
    }

    /**
     * Once the core migration sets ran.
     *
     * @param string $legacyVersion the version reached by the legacy scripts, which recorded it
     */
    public function record(string $legacyVersion): void
    {
        $targetVersion = $this->resolve($legacyVersion);
        if ($targetVersion !== $legacyVersion) {
            $this->updateRepository->updateVersionInformation($targetVersion);
        }
    }
}
