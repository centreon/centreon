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
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class TargetVersionRecorder
{
    public function __construct(
        private UpdateRepository $updateRepository,
        private UpdateScriptFinder $updateScriptFinder,
        #[Autowire(param: 'centreon.version')]
        private string $targetVersion,
    ) {
    }

    public function record(): void
    {
        // Development build: recording the coming release would skip Update-next.php once renamed to it.
        if ($this->updateScriptFinder->hasPendingNextUpdate()) {
            return;
        }

        // A legacy script may have recorded a later version.
        if (version_compare($this->targetVersion, (string) $this->updateRepository->findCurrentVersion(), '>')) {
            $this->updateRepository->updateVersionInformation($this->targetVersion);
        }
    }
}
